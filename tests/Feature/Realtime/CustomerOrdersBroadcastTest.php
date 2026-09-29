<?php

namespace Tests\Feature\Realtime;

use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Orders\Aggregats\AggregatOrderTestCase;

/**
 * Temps réel de la liste des commandes du client (customers/orders/list),
 * sur le canal private-customers.{customerId}.
 */
class CustomerOrdersBroadcastTest extends AggregatOrderTestCase
{
    private function gravierItem(array $overrides = []): array
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
    // Nouvelle commande
    // ---------------------------------------------------------------

    /** @test */
    public function it_broadcasts_a_new_order_on_the_customer_channel()
    {
        Event::fake([OrderCreated::class]);

        $orderId = $this->createOrder($this->gravierItem())->assertOk()->json('data.id');

        Event::assertDispatchedTimes(OrderCreated::class, 1);
        Event::assertDispatched(OrderCreated::class, function (OrderCreated $event) use ($orderId) {
            return $event->orderId === $orderId
                && $event->broadcastOn()[0]->name === 'private-customers.'.$this->customer->id
                && $event->broadcastAs() === 'order.created';
        });
    }

    /** @test */
    public function the_new_order_payload_contains_the_final_amounts()
    {
        $orderId = $this->createOrder($this->gravierItem())->assertOk()->json('data.id');

        // Lu au moment de l'envoi, donc après le calcul des montants par store()
        $payload = (new OrderCreated(Order::findOrFail($orderId)))->broadcastWith();

        $this->assertSame($orderId, $payload['order_id']);
        $this->assertSame(Order::INITIATED, $payload['status']);
        $this->assertSame(Service::AGREGATS_CONSTRUCTION, $payload['service_slug']);
        $this->assertEquals(162500, $payload['order_price']);
        $this->assertEquals(75000, $payload['delivery_price']);
        $this->assertEquals(237500, $payload['total']);
    }

    /** @test */
    public function it_does_not_broadcast_a_rejected_order()
    {
        Event::fake([OrderCreated::class]);

        // La commande est créée puis annulée par le rollback de store()
        $this->createOrder($this->gravierItem(['carrier_id' => 999999]))->assertStatus(400);

        Event::assertNotDispatched(OrderCreated::class);
    }

    /** @test */
    public function it_skips_the_broadcast_if_the_order_was_deleted_in_the_meantime()
    {
        $orderId = $this->createOrder($this->gravierItem())->json('data.id');
        $event = new OrderCreated(Order::findOrFail($orderId));

        Order::findOrFail($orderId)->forceDelete();

        $this->assertFalse($event->broadcastWhen());
    }

    // ---------------------------------------------------------------
    // Changement de statut
    // ---------------------------------------------------------------

    /** @test */
    public function it_broadcasts_status_changes_on_the_customer_channel_too()
    {
        $orderId = $this->createOrder($this->gravierItem())->json('data.id');
        Event::fake([OrderStatusUpdated::class]);

        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$orderId}/cancel")
            ->assertOk();

        Event::assertDispatched(OrderStatusUpdated::class, function (OrderStatusUpdated $event) use ($orderId) {
            $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());

            return $channels === ["private-orders.{$orderId}", 'private-customers.'.$this->customer->id]
                && $event->payload['status'] === Order::CANCELLED
                && $event->payload['service_slug'] === Service::AGREGATS_CONSTRUCTION;
        });
    }

    // ---------------------------------------------------------------
    // Autorisation du canal client
    // ---------------------------------------------------------------

    private function useReverbBroadcaster(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '123456',
        ]);
        Broadcast::forgetDrivers();

        require base_path('routes/channels.php');
    }

    private function authorizeChannel(string $channel)
    {
        return $this->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => $channel,
        ]);
    }

    /** @test */
    public function the_customer_can_listen_to_their_own_channel()
    {
        $this->useReverbBroadcaster();
        $this->actingAs($this->customer, 'api-customers');

        $this->authorizeChannel('private-customers.'.$this->customer->id)
            ->assertOk()
            ->assertJsonPath('auth', fn (string $auth) => str_starts_with($auth, 'test-key:'));
    }

    /** @test */
    public function a_customer_cannot_listen_to_another_customer_channel()
    {
        $this->useReverbBroadcaster();
        $other = Customer::create(['name' => 'Autre client', 'phone' => '2250700000001', 'is_active' => true]);
        $this->actingAs($other, 'api-customers');

        $this->authorizeChannel('private-customers.'.$this->customer->id)->assertForbidden();
    }

    /** @test */
    public function a_guest_cannot_listen_to_a_customer_channel()
    {
        $this->useReverbBroadcaster();

        $this->authorizeChannel('private-customers.'.$this->customer->id)->assertUnauthorized();
    }
}
