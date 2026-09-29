<?php

namespace Tests\Feature\Orders\Aggregats;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Models\OrderItem;
use App\Models\RoutePoint;
use App\Models\Service;
use App\Services\DriverAssignmentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;

class GravierOrderFlowTest extends AggregatOrderTestCase
{
    private function estimatePayload(array $metaOverrides = [], int $quantity = 25): array
    {
        return [
            'service_slug' => Service::AGREGATS_CONSTRUCTION,
            'meta_data' => array_merge([
                'product_type_slug' => 'gravier-515-petit-grain',
                'product_slug' => 'gravier',
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
                'product_type_slug' => 'gravier-515-petit-grain',
                'product_slug' => 'gravier',
                'delivery_type_code' => 'EXPRESS',
            ],
            'quantity' => 25,
            'delivery_price' => 75000,
            'carrier_id' => $this->carrier->id,
            'route_points' => [$this->destination()],
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // Estimation du prix de livraison
    // ---------------------------------------------------------------

    /** @test */
    public function it_estimates_the_delivery_price_from_the_nearest_carrier()
    {
        // 55000 base + 0 km sup. (12 < 45) + (25 - 20) t × 1000 + 10000 frais + 5000 commission
        $this->estimate('gravier', $this->estimatePayload())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.carrier.id', $this->carrier->id)
            ->assertJsonPath('data.amount', 75000)
            ->assertJsonPath('data.amount_with_discount', 75000)
            ->assertJsonPath('data.discount', 0)
            ->assertJsonPath('data.distance', '12.3 km')
            ->assertJsonPath('data.duration', '25 mins')
            ->assertJsonPath('data.delivery_type', 'EXPRESS')
            ->assertJsonPath('data.is_available', true);
    }

    /** @test */
    public function it_applies_the_delivery_type_pricing_to_the_estimate()
    {
        // en-journee : divisé par 2
        $this->estimate('gravier', $this->estimatePayload(['delivery_type_code' => 'en-journee']))
            ->assertOk()
            ->assertJsonPath('data.amount', 37500);
    }

    /** @test */
    public function it_flags_express_as_unavailable_during_rush_hours()
    {
        $this->travelTo(Carbon::parse('2026-09-22 07:30:00'));

        $this->estimate('gravier', $this->estimatePayload())
            ->assertOk()
            ->assertJsonPath('data.is_available', false)
            ->assertJsonPath('data.error_message', fn ($message) => str_contains($message, 'Express'));
    }

    /** @test */
    public function it_rejects_an_estimate_outside_the_delivery_zones()
    {
        $payload = $this->estimatePayload();
        $payload['route_points'] = [$this->destination(['latitude' => 7.69, 'longitude' => -5.03])]; // Bouaké

        $this->estimate('gravier', $payload)
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', "Désolé, votre position n'est pas couverte par notre zone de livraison");
    }

    /** @test */
    public function it_rejects_an_estimate_when_no_carrier_of_the_zone_sells_the_product()
    {
        $this->estimate('gravier', $this->estimatePayload(['product_type_slug' => 'gravier-1525-gros-grain']))
            ->assertStatus(400)
            ->assertJsonPath('message', 'Désolé, aucune carrière à proximité trouvé');
    }

    /** @test */
    public function it_ignores_inactive_carriers_when_estimating()
    {
        $this->carrier->update(['is_active' => false]);

        $this->estimate('gravier', $this->estimatePayload())
            ->assertStatus(400)
            ->assertJsonPath('message', 'Désolé, aucune carrière à proximité trouvé');
    }

    /** @test */
    public function it_requires_quantity_to_estimate()
    {
        $payload = $this->estimatePayload();
        unset($payload['quantity']);

        $this->estimate('gravier', $payload)
            ->assertStatus(400)
            ->assertJsonPath('message', 'quantity is required');
    }

    /** @test */
    public function it_requires_an_authenticated_customer_to_estimate()
    {
        $this->postJson('/api/v1/orders/delivery/gravier/estimate_price', $this->estimatePayload())
            ->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Création de la commande
    // ---------------------------------------------------------------

    /** @test */
    public function it_creates_a_gravier_order_with_its_item_route_points_and_invoice()
    {
        $response = $this->createOrder($this->orderItem())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Order::INITIATED)
            ->assertJsonPath('data.service_slug', Service::AGREGATS_CONSTRUCTION)
            ->assertJsonPath('data.delivery_type_code', 'EXPRESS')
            ->assertJsonPath('data.is_product', true);

        $order = Order::findOrFail($response->json('data.id'));

        $this->assertSame($this->customer->id, $order->customer_id);
        $this->assertEquals(162500, $order->order_price); // 25 t × 6500
        $this->assertEquals(75000, $order->delivery_price);

        $item = OrderItem::where('order_id', $order->id)->sole();
        $this->assertEquals(25, $item->quantity);
        $this->assertSame('T', $item->quantity_unity);
        $this->assertEquals(6500, $item->unit_price);
        $this->assertEquals(162500, $item->order_price);
        $this->assertEquals(237500, $item->total_amount);
        $this->assertEquals(5000, $item->service_due);  // GRAVIER_COMMISSION_OUEGO
        $this->assertEquals(232500, $item->driver_due);
        $this->assertEquals($this->carrier->id, $item->carrier_id);

        $source = RoutePoint::where(['order_id' => $order->id, 'type' => 'source'])->sole();
        $this->assertSame('Carrière Test', $source->address_name);
        $this->assertEquals(5.40, $source->latitude);
        $this->assertEquals(-4.00, $source->longitude);

        $destination = RoutePoint::where(['order_id' => $order->id, 'type' => 'destination'])->sole();
        $this->assertSame($this->customer->id, $destination->customer_id);
        $this->assertEquals(self::DESTINATION_LATITUDE, $destination->latitude);

        $invoice = Invoice::where('order_id', $order->id)->sole();
        $this->assertSame(Invoice::UNPAID, $invoice->status);
        $this->assertEquals(162500, $invoice->subtotal);
        $this->assertEquals(75000, $invoice->fees_delivery);
        $this->assertEquals(237500, $invoice->total);
        $this->assertEquals(0, $invoice->discount);

        $this->assertDatabaseHas('order_histories', ['order_id' => $order->id, 'status' => Order::INITIATED]);
    }

    /** @test */
    public function it_rejects_an_order_without_carrier()
    {
        $item = $this->orderItem();
        unset($item['carrier_id']);

        $this->createOrder($item)
            ->assertStatus(400)
            ->assertJsonPath('message', 'carrier_id is required');

        $this->assertDatabaseCount('orders', 0);
    }

    /** @test */
    public function it_rejects_an_order_without_quantity()
    {
        $item = $this->orderItem();
        unset($item['quantity']);

        $this->createOrder($item)
            ->assertStatus(400)
            ->assertJsonPath('message', 'quantity is required');

        $this->assertDatabaseCount('orders', 0);
    }

    /** @test */
    public function it_rejects_an_order_without_payment_method()
    {
        $this->actingAs($this->customer, 'api-customers')
            ->postJson('/api/v1/orders/create', ['items' => [$this->orderItem()]])
            ->assertStatus(400)
            ->assertJsonPath('message', 'payment_method_code is required');
    }

    /** @test */
    public function it_rejects_an_order_with_an_unknown_product_type()
    {
        $item = $this->orderItem();
        $item['meta_data']['product_type_slug'] = 'gravier-inconnu';

        $this->createOrder($item)
            ->assertStatus(400)
            ->assertJsonPath('message', 'Type de produit introuvable');
    }

    /**
     * @test
     * @dataProvider rejectedOrderItems
     */
    public function it_rolls_back_the_transaction_when_the_order_is_rejected(callable $breakItem, string $expectedMessage)
    {
        $transactionLevel = DB::transactionLevel();

        $this->createOrder($breakItem($this->orderItem()))
            ->assertStatus(400)
            ->assertJsonPath('message', $expectedMessage);

        $this->assertSame($transactionLevel, DB::transactionLevel(), 'La transaction de store() est restée ouverte.');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_histories', 0);
    }

    public static function rejectedOrderItems(): array
    {
        return [
            'service inconnu' => [fn (array $item) => ['service_slug' => 'inconnu'] + $item, 'Service not found'],
            'sans carrier_id' => [fn (array $item) => array_diff_key($item, ['carrier_id' => true]), 'carrier_id is required'],
            'type de produit inconnu' => [
                fn (array $item) => array_replace_recursive($item, ['meta_data' => ['product_type_slug' => 'gravier-inconnu']]),
                'Type de produit introuvable',
            ],
            'carrière inconnue' => [fn (array $item) => ['carrier_id' => 999999] + $item, 'Carrier introuvable'],
        ];
    }

    /** @test */
    public function it_requires_an_authenticated_customer_to_order()
    {
        $this->postJson('/api/v1/orders/create', [
            'payment_method_code' => 'cash',
            'items' => [$this->orderItem()],
        ])->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Confirmation et annulation
    // ---------------------------------------------------------------

    /** @test */
    public function it_confirms_the_order_and_launches_the_driver_lookup()
    {
        $orderId = $this->createOrder($this->orderItem())->json('data.id');

        $this->mock(DriverAssignmentService::class)
            ->shouldReceive('sendInvitations')
            ->once()
            ->with(Mockery::on(fn ($order) => $order->id === $orderId), 10);

        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$orderId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', Order::PERFORMER_LOOKUP)
            ->assertJsonPath('data.is_draft', false);

        $this->assertDatabaseHas('order_histories', ['order_id' => $orderId, 'status' => Order::PERFORMER_LOOKUP]);
    }

    /** @test */
    public function it_cancels_the_order_and_closes_pending_invitations()
    {
        $orderId = $this->createOrder($this->orderItem())->json('data.id');

        $invitation = OrderInvitation::create([
            'order_id' => $orderId,
            'is_waiting_acceptation' => true,
        ]);

        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$orderId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', Order::CANCELLED)
            ->assertJsonPath('data.is_completed', true)
            ->assertJsonPath('data.is_waiting', false);

        $this->assertFalse((bool) $invitation->fresh()->is_waiting_acceptation);
        $this->assertDatabaseHas('order_histories', ['order_id' => $orderId, 'status' => Order::CANCELLED]);
    }

    // ---------------------------------------------------------------
    // Parcours complet
    // ---------------------------------------------------------------

    /** @test */
    public function it_goes_from_estimate_to_confirmed_order()
    {
        $estimate = $this->estimate('gravier', $this->estimatePayload())->assertOk()->json('data');

        $orderId = $this->createOrder($this->orderItem([
            'delivery_price' => $estimate['amount'],
            'carrier_id' => $estimate['carrier']['id'],
        ]))->assertOk()->json('data.id');

        $this->mock(DriverAssignmentService::class)->shouldReceive('sendInvitations')->once();

        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$orderId}/confirm")
            ->assertOk();

        $order = Order::findOrFail($orderId);
        $this->assertSame(Order::PERFORMER_LOOKUP, $order->status);
        $this->assertEquals($estimate['amount'], $order->delivery_price);
        $this->assertEquals(162500 + $estimate['amount'], $order->invoice->total);
    }
}
