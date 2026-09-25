<?php

namespace Tests\Feature\Realtime;

use App\Events\OrderStatusUpdated;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class OrderStatusBroadcastTest extends TestCase
{
    private Customer $customer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = $this->createCustomer();
        $this->order = Order::create([
            'reference' => Order::generateReference(),
            'customer_id' => $this->customer->id,
            'status' => Order::INITIATED,
            'currency_code' => 'XOF',
        ]);
    }

    private function createCustomer(): Customer
    {
        return Customer::create(['name' => 'Client Test', 'phone' => '2250700000000', 'is_active' => true]);
    }

    // ---------------------------------------------------------------
    // Déclenchement de l'event
    // ---------------------------------------------------------------

    /** @test */
    public function it_broadcasts_when_the_status_changes()
    {
        Event::fake([OrderStatusUpdated::class]);

        $this->order->update(['status' => Order::PERFORMER_LOOKUP]);

        Event::assertDispatched(OrderStatusUpdated::class, function (OrderStatusUpdated $event) {
            return $event->orderId === $this->order->id
                && $event->payload['status'] === Order::PERFORMER_LOOKUP
                && $event->payload['previous_status'] === Order::INITIATED
                && $event->payload['driver'] === null;
        });
    }

    /** @test */
    public function it_broadcasts_the_driver_when_one_is_assigned()
    {
        Event::fake([OrderStatusUpdated::class]);
        $driver = Driver::factory()->create(['name' => 'Kouassi']);

        // Même écriture que TripRequestAPIController, qui ne passe pas par newOrderHistory()
        $this->order->update([
            'driver_id' => $driver->id,
            'status' => Order::PERFORMER_FOUND,
            'acceptation_time' => now(),
        ]);

        Event::assertDispatched(OrderStatusUpdated::class, function (OrderStatusUpdated $event) use ($driver) {
            return $event->payload['status'] === Order::PERFORMER_FOUND
                && $event->payload['driver']['id'] === $driver->id
                && $event->payload['driver']['name'] === 'Kouassi'
                && ! array_key_exists('current_balance', $event->payload['driver']);
        });
    }

    /** @test */
    public function it_does_not_broadcast_when_other_fields_change()
    {
        Event::fake([OrderStatusUpdated::class]);

        $this->order->update(['rating' => 5, 'rating_note' => 'Parfait']);

        Event::assertNotDispatched(OrderStatusUpdated::class);
    }

    /** @test */
    public function it_does_not_broadcast_a_change_that_is_rolled_back()
    {
        Event::fake([OrderStatusUpdated::class]);

        try {
            DB::transaction(function () {
                $this->order->update(['status' => Order::CANCELLED]);
                throw new RuntimeException('échec après la mise à jour');
            });
        } catch (RuntimeException) {
        }

        Event::assertNotDispatched(OrderStatusUpdated::class);
    }

    /** @test */
    public function it_broadcasts_on_the_private_order_channel()
    {
        $event = new OrderStatusUpdated($this->order, null);

        $this->assertSame('private-orders.'.$this->order->id, $event->broadcastOn()[0]->name);
        $this->assertSame('order.status.updated', $event->broadcastAs());
    }

    /** @test */
    public function it_broadcasts_when_the_customer_cancels_through_the_api()
    {
        Event::fake([OrderStatusUpdated::class]);

        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$this->order->id}/cancel")
            ->assertOk();

        Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event) => $event->payload['status'] === Order::CANCELLED
            && $event->payload['is_completed'] === true);
    }

    // ---------------------------------------------------------------
    // Autorisation du canal privé
    // ---------------------------------------------------------------

    /**
     * Les tests tournent avec le driver "log", qui accepte tout : on bascule sur
     * Reverb (sans serveur, seule la signature est calculée) et on réenregistre les canaux.
     */
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
    public function the_owner_can_listen_to_the_order_channel()
    {
        $this->useReverbBroadcaster();

        $this->actingAs($this->customer, 'api-customers');

        $this->authorizeChannel("private-orders.{$this->order->id}")
            ->assertOk()
            ->assertJsonStructure(['auth'])
            ->assertJsonPath('auth', fn (string $auth) => str_starts_with($auth, 'test-key:'));
    }

    /** @test */
    public function another_customer_cannot_listen_to_the_order_channel()
    {
        $this->useReverbBroadcaster();

        $this->actingAs($this->createCustomer(), 'api-customers');

        $this->authorizeChannel("private-orders.{$this->order->id}")->assertForbidden();
    }

    /** @test */
    public function a_guest_cannot_listen_to_the_order_channel()
    {
        $this->useReverbBroadcaster();

        $this->authorizeChannel("private-orders.{$this->order->id}")->assertUnauthorized();
    }
}
