<?php

namespace Tests\Feature\Orders\Aggregats;

use App\Models\DeliveryType;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Services\DriverAssignmentService;

/**
 * Le sable ne se vend pas à la tonne mais au camion : le prix du produit vient
 * du forfait choisi (meta_data.pricing) et non du prix du type de produit.
 */
class SableOrderFlowTest extends AggregatOrderTestCase
{
    private const PRICING_10_ROUES = ['name' => '10 roues (12m3)', 'roues' => 10, 'price' => 45000];

    private function estimatePayload(array $metaOverrides = []): array
    {
        return [
            'service_slug' => Service::AGREGATS_CONSTRUCTION,
            'meta_data' => array_merge([
                'product_type_slug' => 'sable-gros-grain',
                'product_slug' => 'sable',
                'delivery_type_code' => 'EXPRESS',
            ], $metaOverrides),
            'quantity' => 1,
            'route_points' => [$this->destination()],
        ];
    }

    private function orderItem(array $metaOverrides = [], array $overrides = []): array
    {
        return array_merge([
            'service_slug' => Service::AGREGATS_CONSTRUCTION,
            'meta_data' => array_merge([
                'product_type_slug' => 'sable-gros-grain',
                'product_slug' => 'sable',
                'delivery_type_code' => 'EXPRESS',
                'pricing' => self::PRICING_10_ROUES,
            ], $metaOverrides),
            'quantity' => 1,
            'delivery_price' => 32300,
            'carrier_id' => $this->carrier->id,
            'route_points' => [$this->destination()],
        ], $overrides);
    }

    /** @test */
    public function it_estimates_the_delivery_price_from_the_nearest_carrier()
    {
        // 20000 base + (12,3 - 5) km × 1000 + 0 frais + 5000 commission
        $this->estimate('sable', $this->estimatePayload())
            ->assertOk()
            ->assertJsonPath('data.carrier.id', $this->carrier->id)
            ->assertJsonPath('data.amount', 32300)
            ->assertJsonPath('data.distance', '12 km')
            ->assertJsonPath('data.is_available', true);
    }

    /** @test */
    public function it_applies_the_night_surcharge_to_the_estimate()
    {
        // de-nuit : +150 % → 32300 × 2,5
        $this->estimate('sable', $this->estimatePayload(['delivery_type_code' => 'de-nuit']))
            ->assertOk()
            ->assertJsonPath('data.amount', 80800);
    }

    /** @test */
    public function it_rejects_an_estimate_when_no_carrier_of_the_zone_sells_the_product()
    {
        $this->estimate('sable', $this->estimatePayload(['product_type_slug' => 'sable-fin']))
            ->assertStatus(400)
            ->assertJsonPath('message', 'Désolé, aucune carrière trouvée à proximité');
    }

    /** @test */
    public function it_creates_a_sable_order_priced_by_truck()
    {
        $response = $this->createOrder($this->orderItem())
            ->assertOk()
            ->assertJsonPath('data.status', Order::INITIATED)
            ->assertJsonPath('data.is_product', true);

        $order = Order::findOrFail($response->json('data.id'));

        $item = OrderItem::where('order_id', $order->id)->sole();
        $this->assertEquals(10, $item->quantity); // nombre de roues du forfait, pas la quantité envoyée
        $this->assertEquals(45000, $item->unit_price);
        $this->assertEquals(45000, $item->order_price);
        $this->assertEquals(77300, $item->total_amount);
        $this->assertEquals(5000, $item->service_due); // SABLE_COMMISSION_OUEGO
        $this->assertEquals(72300, $item->driver_due);
        $this->assertEquals(self::PRICING_10_ROUES, $item->meta_data['pricing']);

        $invoice = Invoice::where('order_id', $order->id)->sole();
        $this->assertEquals(45000, $invoice->subtotal);
        $this->assertEquals(32300, $invoice->fees_delivery);
        $this->assertEquals(77300, $invoice->total);
    }

    /** @test */
    public function it_requires_a_pricing_for_sable()
    {
        $item = $this->orderItem();
        unset($item['meta_data']['pricing']);

        $this->createOrder($item)
            ->assertStatus(400)
            ->assertJsonPath('message', 'Le champ pricing est obligatoire pour le sable');

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * @test
     * @dataProvider invalidPricings
     */
    public function it_rejects_an_invalid_pricing(array $pricing)
    {
        $this->createOrder($this->orderItem(['pricing' => $pricing]))
            ->assertStatus(400)
            ->assertJsonPath('message', 'Le champ pricing est invalide pour le sable');

        $this->assertDatabaseCount('orders', 0);
    }

    public static function invalidPricings(): array
    {
        return [
            'prix manquant' => [['name' => '6 roues', 'roues' => 6]],
            'roues manquantes' => [['name' => '6 roues', 'price' => 25000]],
            'prix nul' => [['name' => '6 roues', 'roues' => 6, 'price' => 0]],
            'roues négatives' => [['name' => '6 roues', 'roues' => -6, 'price' => 25000]],
            'prix non numérique' => [['name' => '6 roues', 'roues' => 6, 'price' => 'gratuit']],
        ];
    }

    /** @test */
    public function it_goes_from_estimate_to_confirmed_order()
    {
        $estimate = $this->estimate('sable', $this->estimatePayload())->assertOk()->json('data');

        $orderId = $this->createOrder($this->orderItem([], [
            'delivery_price' => $estimate['amount'],
            'carrier_id' => $estimate['carrier']['id'],
        ]))->assertOk()->json('data.id');

        $this->mock(DriverAssignmentService::class)->shouldReceive('sendInvitations')->once();

        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$orderId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', Order::PERFORMER_LOOKUP);

        $this->assertEquals(45000 + $estimate['amount'], Order::findOrFail($orderId)->invoice->total);
    }

    /** @test */
    public function it_marks_express_unavailable_in_the_estimate_during_rush_hours()
    {
        $this->travelTo(now()->setTime(18, 0));

        $this->estimate('sable', $this->estimatePayload())
            ->assertOk()
            ->assertJsonPath('data.is_available', false)
            ->assertJsonPath('data.error_message', DeliveryType::EXPRESS_UNAVAILABLE_MESSAGE);
    }

    /** @test */
    public function it_rejects_an_express_order_during_rush_hours()
    {
        $this->travelTo(now()->setTime(18, 0));

        $this->createOrder($this->orderItem())
            ->assertStatus(404)
            ->assertJsonPath('message', DeliveryType::EXPRESS_UNAVAILABLE_MESSAGE);

        $this->assertDatabaseCount('orders', 0);
    }

    /** @test */
    public function it_accepts_an_express_order_from_19h30()
    {
        $this->travelTo(now()->setTime(19, 30));

        $this->createOrder($this->orderItem())
            ->assertOk()
            ->assertJsonPath('data.status', Order::INITIATED);
    }

    /** @test */
    public function it_marks_en_journee_unavailable_in_the_estimate_from_the_cutoff_hour()
    {
        $this->travelTo(now()->setTime(12, 0));

        $this->estimate('sable', $this->estimatePayload(['delivery_type_code' => DeliveryType::TYPE_EN_JOURNEE]))
            ->assertOk()
            ->assertJsonPath('data.is_available', false)
            ->assertJsonPath('data.error_message', 'Vous pouvez passer une course en journée uniquement de 06H00 à 12H00.');
    }

    /** @test */
    public function it_rejects_an_en_journee_order_from_the_cutoff_hour()
    {
        $this->travelTo(now()->setTime(12, 0));

        $this->createOrder($this->orderItem(['delivery_type_code' => DeliveryType::TYPE_EN_JOURNEE]))
            ->assertStatus(404)
            ->assertJsonPath('message', 'Vous pouvez passer une course en journée uniquement de 06H00 à 12H00.');

        $this->assertDatabaseCount('orders', 0);
    }

    /** @test */
    public function it_accepts_an_en_journee_order_before_the_cutoff_hour()
    {
        $this->travelTo(now()->setTime(11, 59));

        $this->createOrder($this->orderItem(['delivery_type_code' => DeliveryType::TYPE_EN_JOURNEE]))
            ->assertOk()
            ->assertJsonPath('data.status', Order::INITIATED);
    }
}
