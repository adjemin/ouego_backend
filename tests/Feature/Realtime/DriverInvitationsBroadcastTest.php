<?php

namespace Tests\Feature\Realtime;

use App\Events\OrderAssigned;
use App\Models\Driver;
use App\Models\OrderInvitation;
use App\Models\Service;
use Illuminate\Support\Facades\Broadcast;
use Tests\Feature\Orders\Aggregats\AggregatOrderTestCase;

/**
 * Temps réel des invitations chauffeur (drivers/orders_invitations/list),
 * sur le canal private-drivers.{driverId}.
 */
class DriverInvitationsBroadcastTest extends AggregatOrderTestCase
{
    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = Driver::factory()->create();
    }

    private function inviteDriverOnNewOrder(): OrderInvitation
    {
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

        return OrderInvitation::create([
            'driver_id' => $this->driver->id,
            'order_id' => $orderId,
            'is_waiting_acceptation' => true,
        ]);
    }

    // ---------------------------------------------------------------
    // Event
    // ---------------------------------------------------------------

    /** @test */
    public function it_broadcasts_the_invitation_on_the_driver_channel()
    {
        $event = new OrderAssigned($this->inviteDriverOnNewOrder());

        $this->assertInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class, $event);
        $this->assertSame('private-drivers.'.$this->driver->id, $event->broadcastOn()[0]->name);
        $this->assertSame('order.invitation.created', $event->broadcastAs());
        $this->assertTrue($event->broadcastWhen());
    }

    /** @test */
    public function the_payload_describes_the_order_for_the_driver()
    {
        $invitation = $this->inviteDriverOnNewOrder();

        $payload = (new OrderAssigned($invitation))->broadcastWith();

        $this->assertSame($invitation->id, $payload['invitation_id']);
        $this->assertSame($invitation->order_id, $payload['order_id']);
        $this->assertSame(Service::AGREGATS_CONSTRUCTION, $payload['order']['service_slug']);
        $this->assertSame('EXPRESS', $payload['order']['delivery_type_code']);
        $this->assertEquals(232500, $payload['order']['driver_due']); // total 237500 - commission 5000

        $points = collect($payload['order']['route_points'])->keyBy('type');
        $this->assertSame('Carrière Test', $points['source']['address_name']);
        $this->assertEquals(self::DESTINATION_LATITUDE, $points['destination']['latitude']);
        $this->assertSame(['type', 'address_name', 'latitude', 'longitude'], array_keys($points['source']));
    }

    /** @test */
    public function it_does_not_broadcast_an_invitation_that_is_no_longer_pending()
    {
        $invitation = $this->inviteDriverOnNewOrder();
        $event = new OrderAssigned($invitation);

        // Acceptée, refusée ou expirée avant que la queue traite le broadcast
        $invitation->update(['is_waiting_acceptation' => false]);

        $this->assertFalse($event->broadcastWhen());
    }

    // ---------------------------------------------------------------
    // Autorisation du canal chauffeur
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

    private function authorizeChannel(string $endpoint, string $channel, ?string $token = null)
    {
        return $this->withHeaders($token ? ['Authorization' => "Bearer {$token}"] : [])
            ->postJson($endpoint, ['socket_id' => '1234.5678', 'channel_name' => $channel]);
    }

    /** @test */
    public function the_driver_can_listen_to_their_own_channel()
    {
        $this->useReverbBroadcaster();
        $token = auth('api-drivers')->login($this->driver);

        $this->authorizeChannel('/api/v1/drivers/broadcasting/auth', 'private-drivers.'.$this->driver->id, $token)
            ->assertOk()
            ->assertJsonPath('auth', fn (string $auth) => str_starts_with($auth, 'test-key:'));
    }

    /** @test */
    public function a_driver_cannot_listen_to_another_driver_channel()
    {
        $this->useReverbBroadcaster();
        $token = auth('api-drivers')->login(Driver::factory()->create());

        $this->authorizeChannel('/api/v1/drivers/broadcasting/auth', 'private-drivers.'.$this->driver->id, $token)
            ->assertForbidden();
    }

    /** @test */
    public function a_customer_token_is_rejected_on_the_driver_endpoint()
    {
        $this->useReverbBroadcaster();
        // Même id que le chauffeur : seul le verrou de sujet du JWT (lock_subject) les distingue
        $this->customer->forceFill(['id' => $this->driver->id])->save();
        $token = auth('api-customers')->login($this->customer);

        $this->authorizeChannel('/api/v1/drivers/broadcasting/auth', 'private-drivers.'.$this->driver->id, $token)
            ->assertUnauthorized();
    }

    /** @test */
    public function a_customer_cannot_listen_to_a_driver_channel_through_the_customer_endpoint()
    {
        $this->useReverbBroadcaster();
        $this->customer->forceFill(['id' => $this->driver->id])->save();
        $token = auth('api-customers')->login($this->customer);

        $this->authorizeChannel('/api/v1/broadcasting/auth', 'private-drivers.'.$this->driver->id, $token)
            ->assertForbidden();
    }

    /** @test */
    public function a_guest_cannot_listen_to_a_driver_channel()
    {
        $this->useReverbBroadcaster();

        $this->authorizeChannel('/api/v1/drivers/broadcasting/auth', 'private-drivers.'.$this->driver->id)
            ->assertUnauthorized();
    }
}
