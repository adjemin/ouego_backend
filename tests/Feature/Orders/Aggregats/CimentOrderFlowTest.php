<?php

namespace Tests\Feature\Orders\Aggregats;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Services\DriverAssignmentService;

/**
 * Tarifs ciment définis dans AggregatOrderTestCase::seedCiment() (les seeders les mettent à 0).
 */
class CimentOrderFlowTest extends AggregatOrderTestCase
{
    private function estimatePayload(array $metaOverrides = [], int $quantity = 50): array
    {
        return [
            'service_slug' => Service::AGREGATS_CONSTRUCTION,
            'meta_data' => array_merge([
                'product_type_slug' => 'ciment-portland',
                'product_slug' => 'ciment',
                'delivery_type_code' => 'EXPRESS',
            ], $metaOverrides),
            'quantity' => $quantity,
            'route_points' => [$this->destination()],
        ];
    }

    private function orderItem(array $overrides = []): array
    {
        return array_merge([
            'service_slug' => Service::AGREGATS_CONSTRUCTION,
            'meta_data' => [
                'product_type_slug' => 'ciment-portland',
                'product_slug' => 'ciment',
                'delivery_type_code' => 'EXPRESS',
            ],
            'quantity' => 50,
            'delivery_price' => 44000,
            'carrier_id' => $this->carrier->id,
            'route_points' => [$this->destination()],
        ], $overrides);
    }

    /** @test */
    public function it_estimates_the_delivery_price_from_the_nearest_carrier()
    {
        // 30000 base + (12 - 10) km × 500 + (50 - 10) t × 200 + 2000 frais + 3000 commission
        $this->estimate('ciment', $this->estimatePayload())
            ->assertOk()
            ->assertJsonPath('data.carrier.id', $this->carrier->id)
            ->assertJsonPath('data.amount', 44000)
            ->assertJsonPath('data.distance', '12.3 km')
            ->assertJsonPath('data.is_available', true);
    }

    /**
     * @test
     * @dataProvider deliveryTypes
     */
    public function it_applies_the_delivery_type_to_the_estimate(string $deliveryType, int $expectedAmount)
    {
        $this->estimate('ciment', $this->estimatePayload(['delivery_type_code' => $deliveryType]))
            ->assertOk()
            ->assertJsonPath('data.amount', $expectedAmount);
    }

    public static function deliveryTypes(): array
    {
        return [
            'express' => ['EXPRESS', 44000],
            'en journée (÷2)' => ['en-journee', 22000],
            'de nuit (+150 %)' => ['de-nuit', 110000],
            'en semaine (÷3)' => ['en-semaine', 14700], // 14666,67 arrondi à la centaine supérieure
        ];
    }

    /** @test */
    public function it_scales_the_estimate_with_the_quantity()
    {
        // 10 t de plus → +10 × 200
        $this->estimate('ciment', $this->estimatePayload([], 60))
            ->assertOk()
            ->assertJsonPath('data.amount', 46000);
    }

    /** @test */
    public function it_rejects_an_estimate_outside_the_delivery_zones()
    {
        $payload = $this->estimatePayload();
        $payload['route_points'] = [$this->destination(['latitude' => 7.69, 'longitude' => -5.03])];

        $this->estimate('ciment', $payload)
            ->assertStatus(400)
            ->assertJsonPath('message', "Désolé, votre position n'est pas couverte par notre zone de livraison");
    }

    /** @test */
    public function it_creates_a_ciment_order_priced_by_quantity()
    {
        $response = $this->createOrder($this->orderItem())
            ->assertOk()
            ->assertJsonPath('data.status', Order::INITIATED)
            ->assertJsonPath('data.is_product', true);

        $order = Order::findOrFail($response->json('data.id'));

        $item = OrderItem::where('order_id', $order->id)->sole();
        $this->assertEquals(50, $item->quantity);
        $this->assertEquals(5000, $item->unit_price);
        $this->assertEquals(250000, $item->order_price);
        $this->assertEquals(294000, $item->total_amount);
        $this->assertEquals(3000, $item->service_due); // CIMENT_COMMISSION_OUEGO
        $this->assertEquals(291000, $item->driver_due);

        $invoice = Invoice::where('order_id', $order->id)->sole();
        $this->assertEquals(250000, $invoice->subtotal);
        $this->assertEquals(44000, $invoice->fees_delivery);
        $this->assertEquals(294000, $invoice->total);
    }

    /** @test */
    public function it_rejects_an_order_with_an_unknown_carrier()
    {
        $this->createOrder($this->orderItem(['carrier_id' => 999999]))
            ->assertStatus(400)
            ->assertJsonPath('message', 'Carrier introuvable');
    }

    /** @test */
    public function it_goes_from_estimate_to_confirmed_order()
    {
        $estimate = $this->estimate('ciment', $this->estimatePayload())->assertOk()->json('data');

        $orderId = $this->createOrder($this->orderItem([
            'delivery_price' => $estimate['amount'],
            'carrier_id' => $estimate['carrier']['id'],
        ]))->assertOk()->json('data.id');

        $this->mock(DriverAssignmentService::class)->shouldReceive('sendInvitations')->once();

        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$orderId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', Order::PERFORMER_LOOKUP);

        $this->assertEquals(250000 + $estimate['amount'], Order::findOrFail($orderId)->invoice->total);
    }
}
