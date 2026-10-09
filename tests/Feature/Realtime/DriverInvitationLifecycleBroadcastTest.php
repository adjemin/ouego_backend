<?php

namespace Tests\Feature\Realtime;

use App\Events\OrderInvitationCancelled;
use App\Events\OrderInvitationUpdated;
use App\Jobs\ProcessPendingOrderAssignments;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Models\Service;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Orders\Aggregats\AggregatOrderTestCase;

/**
 * Suite de vie d'une invitation en temps réel, sur private-drivers.{driverId} :
 *  - order.invitation.cancelled : l'invitation sort de la liste du chauffeur ;
 *  - order.invitation.updated : l'invitation affichée a changé.
 */
class DriverInvitationLifecycleBroadcastTest extends AggregatOrderTestCase
{
    private Order $order;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([OrderInvitationCancelled::class, OrderInvitationUpdated::class]);

        $orderId = $this->createOrder([
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
        ])->assertOk()->json('data.id');

        $this->order = Order::findOrFail($orderId);
        $this->order->update([
            'status' => Order::PERFORMER_LOOKUP,
            'order_date' => now(),
            'is_draft' => false,
        ]);

        $this->driver = Driver::factory()->create();
    }

    private function invite(Driver $driver, bool $waiting = true): OrderInvitation
    {
        return OrderInvitation::create([
            'driver_id' => $driver->id,
            'order_id' => $this->order->id,
            'is_waiting_acceptation' => $waiting,
        ]);
    }

    private function assertCancelled(OrderInvitation $invitation, string $reason): void
    {
        Event::assertDispatched(OrderInvitationCancelled::class, fn (OrderInvitationCancelled $event) =>
            $event->payload['invitation_id'] === $invitation->id && $event->payload['reason'] === $reason
        );
    }

    // ---------------------------------------------------------------
    // order.invitation.cancelled
    // ---------------------------------------------------------------

    /** @test */
    public function the_cancellation_is_broadcast_on_the_driver_channel()
    {
        $invitation = $this->invite($this->driver);
        $event = new OrderInvitationCancelled($invitation, OrderInvitationCancelled::EXPIRED);

        // Le payload survit à la suppression de l'invitation avant le passage du worker
        $invitation->forceDelete();

        $this->assertSame('private-drivers.'.$this->driver->id, $event->broadcastOn()[0]->name);
        $this->assertSame('order.invitation.cancelled', $event->broadcastAs());
        $this->assertSame([
            'invitation_id' => $invitation->id,
            'order_id' => $this->order->id,
            'reason' => 'expired',
        ], collect($event->broadcastWith())->except('cancelled_at')->all());
    }

    /** @test */
    public function cancelling_the_order_notifies_every_invited_driver()
    {
        $waiting = $this->invite($this->driver);
        $other = $this->invite(Driver::factory()->create());
        $refused = $this->invite(Driver::factory()->create(), false);

        $this->actingAs($this->customer, 'api-customers')
            ->putJson("api/v1/orders/{$this->order->id}/cancel")
            ->assertOk();

        $this->assertCancelled($waiting, OrderInvitationCancelled::ORDER_CANCELLED);
        $this->assertCancelled($other, OrderInvitationCancelled::ORDER_CANCELLED);
        Event::assertDispatchedTimes(OrderInvitationCancelled::class, 2);
        $this->assertFalse($waiting->fresh()->is_waiting_acceptation);
        $this->assertNotNull($refused->fresh());
    }

    /** @test */
    public function an_acceptance_notifies_the_other_invited_drivers()
    {
        $accepted = $this->invite($this->driver);
        $other = $this->invite(Driver::factory()->create());
        $token = auth('api-drivers')->login($this->driver);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->putJson("api/v1/drivers/orders_invitations/{$accepted->id}/accept");

        $this->assertCancelled($other, OrderInvitationCancelled::TAKEN_BY_ANOTHER_DRIVER);
        Event::assertDispatchedTimes(OrderInvitationCancelled::class, 1);
        $this->assertFalse($other->fresh()->is_waiting_acceptation);
        $this->assertNotNull($other->fresh()->rejection_time);
    }

    /** @test */
    public function an_expired_invitation_is_removed_from_the_driver_list()
    {
        $invitation = $this->invite($this->driver);

        $this->travel(3)->minutes();
        (new ProcessPendingOrderAssignments())->handle();

        $this->assertCancelled($invitation, OrderInvitationCancelled::EXPIRED);
        $this->assertSoftDeleted($invitation);
    }

    /** @test */
    public function the_end_of_the_driver_lookup_closes_the_remaining_invitations()
    {
        $this->travelTo($this->order->performerLookupDeadline()->addMinute());
        $invitation = $this->invite($this->driver);

        (new ProcessPendingOrderAssignments())->handle();

        $this->assertSame(Order::PERFORMER_NOT_FOUND, $this->order->fresh()->status);
        $this->assertCancelled($invitation, OrderInvitationCancelled::EXPIRED);
        $this->assertFalse($invitation->fresh()->is_waiting_acceptation);
    }

    /** @test */
    public function deleting_waiting_invitations_notifies_their_drivers_only()
    {
        $waiting = $this->invite($this->driver);
        $closed = $this->invite(Driver::factory()->create(), false);

        OrderInvitation::deleteWaiting(
            OrderInvitation::where('order_id', $this->order->id),
            OrderInvitationCancelled::REASSIGNED
        );

        $this->assertCancelled($waiting, OrderInvitationCancelled::REASSIGNED);
        Event::assertDispatchedTimes(OrderInvitationCancelled::class, 1);
        $this->assertSoftDeleted($waiting);
        $this->assertNotSoftDeleted($closed);
    }

    // ---------------------------------------------------------------
    // order.invitation.updated
    // ---------------------------------------------------------------

    /** @test */
    public function a_new_driver_due_updates_the_waiting_invitations()
    {
        $waiting = $this->invite($this->driver);
        $this->invite(Driver::factory()->create(), false);

        Invoice::where('order_id', $this->order->id)->first()->update(['driver_due' => 250000]);

        Event::assertDispatchedTimes(OrderInvitationUpdated::class, 1);
        Event::assertDispatched(OrderInvitationUpdated::class, function (OrderInvitationUpdated $event) use ($waiting) {
            return $event->broadcastOn()[0]->name === 'private-drivers.'.$this->driver->id
                && $event->broadcastAs() === 'order.invitation.updated'
                && $event->broadcastWith()['invitation_id'] === $waiting->id
                && $event->broadcastWith()['order']['driver_due'] == 250000;
        });
    }

    /** @test */
    public function a_change_of_delivery_type_updates_the_waiting_invitations()
    {
        $this->invite($this->driver);

        $this->order->update(['delivery_type_code' => 'STANDARD']);

        Event::assertDispatched(OrderInvitationUpdated::class, fn (OrderInvitationUpdated $event) =>
            $event->broadcastWith()['order']['delivery_type_code'] === 'STANDARD'
        );
    }

    /** @test */
    public function a_status_change_alone_does_not_update_the_invitations()
    {
        $this->invite($this->driver);

        $this->order->update(['status' => Order::PERFORMER_NOT_FOUND]);

        Event::assertNotDispatched(OrderInvitationUpdated::class);
    }
}
