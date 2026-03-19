<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TripService;
use App\Models\Order;
use App\Models\Carrier;
use App\Models\Driver;
use App\Models\TripRequest;
use App\Models\OrderInvitation;
use App\Jobs\AssignTimeoutCheck;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use App\Events\OrderAssigned;
use App\Events\CustomerNotificationCreated;
use App\Models\CustomerNotification;
use Mockery;

class TripServiceTest extends TestCase
{
    // use \Illuminate\Foundation\Testing\RefreshDatabase;

    /** @test */
    public function it_creates_trip_request_and_invitations_when_drivers_exist()
    {
        $carrier = Carrier::factory()->create();
        $order = Order::factory()->create();
        $drivers = Driver::factory()->count(2)->create();

        $service = new TripService();
        $tripRequest = $service->createRequest($drivers->all(), $carrier, $order);

        $this->assertDatabaseHas('trip_requests', [
            'id' => $tripRequest->id,
            'carrier_id' => $carrier->id,
            'order_id' => $order->id,
        ]);

        $this->assertEquals(2, OrderInvitation::where('trip_request_id', $tripRequest->id)->count());
    }

    /** @test */
    public function it_marks_trip_as_failed_when_no_driver_found()
    {
        $carrier = Carrier::factory()->create();
        $order = Order::factory()->create();
        $service = new TripService();

        $tripRequest = $service->createRequest([], $carrier, $order);

        $this->assertEquals('failed', $tripRequest->fresh()->status);
    }

    /** @test */
    public function it_dispatches_driver_invitation_and_event()
    {
        Event::fake();
        Queue::fake();

        $carrier = Carrier::factory()->create();
        $order = Order::factory()->create();
        $driver = Driver::factory()->create();
        $service = new TripService();

        $tripRequest = $service->createRequest([$driver], $carrier, $order);

        // Simule le premier envoi d'invitation
        $service->dispatchNextDriverInvitation($tripRequest);

        $invitation = $tripRequest->invitations()->first();
        $this->assertEquals(OrderInvitation::NOTIFIED, $invitation->fresh()->status);

        Event::assertDispatched(OrderAssigned::class);
        Queue::assertPushed(AssignTimeoutCheck::class);
    }

    /** @test */
    public function it_notifies_customer_when_no_driver_is_found()
    {
        Event::fake();

        $order = Order::factory()->create();
        $tripRequest = TripRequest::factory()->create([
            'order_id' => $order->id,
        ]);

        $service = new TripService();
        $service->notifyNoDriverFound($tripRequest);

        $this->assertDatabaseHas('customer_notifications', [
            'customer_id' => $order->customer_id,
            'data_id' => $order->id,
            'is_read' => false,
        ]);

        Event::assertDispatched(CustomerNotificationCreated::class);
    }
}
