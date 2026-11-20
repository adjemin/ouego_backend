<?php

namespace Tests\Feature\Orders;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Models\RoutePoint;
use App\Models\Service;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Agrégats : le client peut annuler tant que le chauffeur n'a pas chargé à la carrière.
 */
class AggregatOrderCancellationTest extends TestCase
{
    private Customer $customer;

    private Driver $driver;

    private Order $order;

    private RoutePoint $carrierPoint;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->customer = Customer::create(['name' => 'Client', 'phone' => '2250700000011', 'is_active' => true]);
        $this->driver = Driver::factory()->create();

        $this->order = Order::create([
            'reference' => Order::generateReference(),
            'customer_id' => $this->customer->id,
            'service_slug' => Service::AGREGATS_CONSTRUCTION,
            'status' => Order::PERFORMER_LOOKUP,
            'currency_code' => 'XOF',
            'is_draft' => false,
            'is_completed' => false,
        ]);

        $this->carrierPoint = RoutePoint::create([
            'order_id' => $this->order->id,
            'type' => RoutePoint::POINT_TYPE_SOURCE,
            'status' => RoutePoint::WAITING,
            'latitude' => 5.37,
            'longitude' => -4.00,
        ]);
        RoutePoint::create([
            'order_id' => $this->order->id,
            'type' => RoutePoint::POINT_TYPE_DESTINATION,
            'status' => RoutePoint::WAITING,
            'latitude' => 5.30,
            'longitude' => -4.01,
        ]);
    }

    private function cancel()
    {
        return $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$this->order->id}/cancel");
    }

    private function assignDriver(array $attributes = []): void
    {
        $this->order->update(array_merge(['driver_id' => $this->driver->id, 'status' => Order::PERFORMER_FOUND], $attributes));
    }

    private function assertCancelled(): void
    {
        $order = $this->order->fresh();
        $this->assertSame(Order::CANCELLED, $order->status);
        $this->assertTrue($order->is_completed);
    }

    private function assertNotCancelled(string $expectedStatus): void
    {
        $this->assertSame($expectedStatus, $this->order->fresh()->status);
    }

    /** @test */
    public function it_can_be_cancelled_while_looking_for_a_driver()
    {
        $this->cancel()->assertOk();

        $this->assertCancelled();
    }

    /** @test */
    public function it_can_be_cancelled_once_a_driver_is_assigned()
    {
        $this->assignDriver();

        $this->cancel()->assertOk();

        $this->assertCancelled();
    }

    /** @test */
    public function it_can_be_cancelled_while_the_driver_heads_to_the_carrier()
    {
        $this->assignDriver(['is_started' => true, 'is_running' => true]);
        $this->carrierPoint->update(['status' => RoutePoint::STARTED, 'is_started' => true]);

        $this->cancel()->assertOk();

        $this->assertCancelled();
    }

    /** @test */
    public function it_can_be_cancelled_when_the_driver_has_arrived_at_the_carrier_but_not_loaded()
    {
        $this->assignDriver(['status' => Order::PICKUP_ARRIVED, 'is_started' => true, 'is_running' => true]);
        $this->carrierPoint->update(['status' => RoutePoint::ARRIVED, 'is_arrived' => true]);

        $this->cancel()->assertOk();

        $this->assertCancelled();
    }

    /** @test */
    public function it_cannot_be_cancelled_once_the_driver_has_loaded()
    {
        $this->assignDriver(['status' => Order::PICKUPED, 'is_started' => true, 'is_running' => true]);
        $this->carrierPoint->update(['status' => RoutePoint::SUCCESS, 'is_completed' => true, 'is_successful' => true]);

        $this->cancel()
            ->assertStatus(400)
            ->assertJsonPath('message', 'Vous ne pouvez plus annuler cette commande : le chauffeur a déjà chargé la marchandise');

        $this->assertNotCancelled(Order::PICKUPED);
    }

    /** @test */
    public function the_validated_carrier_point_is_enough_to_block_the_cancellation()
    {
        // Statut de commande pas encore à jour, mais point de chargement validé
        $this->assignDriver(['status' => Order::PICKUP_ARRIVED, 'is_started' => true]);
        $this->carrierPoint->update(['status' => RoutePoint::SUCCESS, 'is_completed' => true, 'is_successful' => true]);

        $this->cancel()->assertStatus(400);

        $this->assertNotCancelled(Order::PICKUP_ARRIVED);
    }

    /** @test */
    public function it_cannot_be_cancelled_once_delivered()
    {
        foreach ([Order::DELIVERY_ARRIVED, Order::DELIVERED] as $status) {
            $this->assignDriver(['status' => $status, 'is_started' => true]);

            $this->cancel()->assertStatus(400);

            $this->assertNotCancelled($status);
        }
    }

    /** @test */
    public function a_finished_or_already_cancelled_order_cannot_be_cancelled_again()
    {
        $this->assignDriver(['status' => Order::DELIVERED_FINISH, 'is_completed' => true]);
        $this->cancel()->assertStatus(400);
        $this->assertNotCancelled(Order::DELIVERED_FINISH);

        $this->order->update(['status' => Order::CANCELLED, 'is_completed' => true]);
        $this->cancel()
            ->assertStatus(400)
            ->assertJsonPath('message', 'Cette commande est déjà terminée ou annulée');
    }

    /** @test */
    public function cancelling_withdraws_the_waiting_invitations()
    {
        $invitation = OrderInvitation::create([
            'order_id' => $this->order->id,
            'driver_id' => $this->driver->id,
            'is_waiting_acceptation' => true,
        ]);

        $this->cancel()->assertOk();

        $this->assertFalse((bool) $invitation->fresh()->is_waiting_acceptation);
    }
}
