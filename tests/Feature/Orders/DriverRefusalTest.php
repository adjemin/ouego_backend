<?php

namespace Tests\Feature\Orders;

use App\Events\OrderAssigned;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Models\Service;
use App\Services\DriverAssignmentService;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Orders\Aggregats\AggregatOrderTestCase;

/**
 * Refus d'une invitation : le chauffeur qui refuse laisse sa place à d'autres,
 * n'est plus notifié pour cette commande, et la recherche repart aussitôt.
 */
class DriverRefusalTest extends AggregatOrderTestCase
{
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    // ---------------------------------------------------------------
    // Invitation et notification
    // ---------------------------------------------------------------

    /** @test */
    public function a_new_invitation_notifies_the_driver_once()
    {
        Event::fake([OrderAssigned::class]);
        $driver = Driver::factory()->create();

        $first = OrderInvitation::inviteDriver($this->order->id, $driver);
        $second = OrderInvitation::inviteDriver($this->order->id, $driver);

        $this->assertNotNull($first);
        $this->assertTrue($first->is_waiting_acceptation);
        $this->assertNull($second);
        $this->assertSame(1, OrderInvitation::where('order_id', $this->order->id)->count());
        Event::assertDispatchedTimes(OrderAssigned::class, 1);
    }

    /** @test */
    public function a_driver_who_refused_is_not_notified_again()
    {
        Event::fake([OrderAssigned::class]);
        $driver = Driver::factory()->create();
        $this->invitation($driver, refused: true);

        $this->assertNull(OrderInvitation::inviteDriver($this->order->id, $driver));
        Event::assertNotDispatched(OrderAssigned::class);
    }

    // ---------------------------------------------------------------
    // Exclusion de la recherche
    // ---------------------------------------------------------------

    /** @test */
    public function the_search_excludes_drivers_whose_invitation_is_closed_for_this_order()
    {
        $refused = Driver::factory()->create();
        $pending = Driver::factory()->create();
        $refusedElsewhere = Driver::factory()->create();
        $neverInvited = Driver::factory()->create();

        $this->invitation($refused, refused: true);
        $this->invitation($pending);
        OrderInvitation::create([
            'driver_id' => $refusedElsewhere->id,
            'order_id' => $this->order->id + 1000,
            'is_waiting_acceptation' => false,
            'rejection_time' => now(),
        ]);

        $ids = Driver::withoutClosedInvitationFor($this->order->id)->pluck('id');

        $this->assertNotContains($refused->id, $ids);
        $this->assertContains($pending->id, $ids);
        $this->assertContains($refusedElsewhere->id, $ids);
        $this->assertContains($neverInvited->id, $ids);
    }

    /** @test */
    public function an_expired_invitation_does_not_exclude_the_driver()
    {
        $driver = Driver::factory()->create();
        $this->invitation($driver)->delete(); // supprimée par la tâche automatique après 2 min

        $this->assertContains($driver->id, Driver::withoutClosedInvitationFor($this->order->id)->pluck('id'));
    }

    // ---------------------------------------------------------------
    // Relance immédiate au refus
    // ---------------------------------------------------------------

    /** @test */
    public function refusing_the_last_pending_invitation_relaunches_the_search()
    {
        $driver = Driver::factory()->create();
        $invitation = $this->invitation($driver);

        $this->mock(DriverAssignmentService::class)
            ->shouldReceive('sendInvitations')
            ->once()
            ->withArgs(fn ($order, $distance) => $order->id === $this->order->id && $distance === 10);

        $this->refuse($driver, $invitation)->assertOk();

        $this->assertNotNull($invitation->fresh()->rejection_time);
    }

    /** @test */
    public function refusing_does_not_relaunch_while_other_invitations_are_pending()
    {
        $driver = Driver::factory()->create();
        $invitation = $this->invitation($driver);
        $this->invitation(Driver::factory()->create());

        $this->mock(DriverAssignmentService::class)->shouldNotReceive('sendInvitations');

        $this->refuse($driver, $invitation)->assertOk();
    }

    /** @test */
    public function refusing_does_not_relaunch_once_a_driver_is_assigned()
    {
        $driver = Driver::factory()->create();
        $invitation = $this->invitation($driver);
        $this->order->update(['driver_id' => Driver::factory()->create()->id, 'status' => Order::PERFORMER_FOUND]);

        $this->mock(DriverAssignmentService::class)->shouldNotReceive('sendInvitations');

        $this->refuse($driver, $invitation)->assertOk();
    }

    /** @test */
    public function refusing_does_not_relaunch_after_the_lookup_deadline()
    {
        $driver = Driver::factory()->create();
        $invitation = $this->invitation($driver);
        $this->travel(Order::PERFORMER_LOOKUP_TIMEOUT + 1)->minutes();

        $this->mock(DriverAssignmentService::class)->shouldNotReceive('sendInvitations');

        $this->refuse($driver, $invitation)->assertOk();
    }

    // ---------------------------------------------------------------
    // Sécurité : un chauffeur ne traite que ses propres invitations
    // ---------------------------------------------------------------

    /** @test */
    public function a_driver_cannot_refuse_another_drivers_invitation()
    {
        $invitation = $this->invitation(Driver::factory()->create());

        $this->refuse(Driver::factory()->create(), $invitation)
            ->assertStatus(404)
            ->assertJsonPath('message', 'Invitation introuvable');

        $invitation->refresh();
        $this->assertTrue($invitation->is_waiting_acceptation);
        $this->assertNull($invitation->rejection_time);
    }

    /** @test */
    public function a_driver_cannot_accept_another_drivers_invitation()
    {
        $invitation = $this->invitation(Driver::factory()->create());
        $token = auth('api-drivers')->login(Driver::factory()->create());

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->putJson("/api/v1/drivers/orders_invitations/{$invitation->id}/accept")
            ->assertStatus(400)
            ->assertJsonPath('message', 'Invitation introuvable');

        $this->assertTrue($invitation->fresh()->is_waiting_acceptation);
        $this->assertNull($this->order->fresh()->driver_id);
    }

    /**
     * @test
     * @dataProvider legacyRoutes
     */
    public function the_legacy_routes_require_an_authenticated_driver(string $action)
    {
        $invitation = $this->invitation(Driver::factory()->create());

        $this->putJson("/api/v1/order_invitations/{$invitation->id}/{$action}")->assertStatus(401);

        $this->assertTrue($invitation->fresh()->is_waiting_acceptation);
    }

    public static function legacyRoutes(): array
    {
        return [
            'accept' => ['accept'],
            'refuse' => ['refuse'],
        ];
    }

    /** @test */
    public function the_legacy_refuse_route_still_works_for_the_invited_driver()
    {
        $driver = Driver::factory()->create();
        $invitation = $this->invitation($driver);
        $this->invitation(Driver::factory()->create()); // évite la relance de la recherche
        $token = auth('api-drivers')->login($driver);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->putJson("/api/v1/order_invitations/{$invitation->id}/refuse")
            ->assertOk();

        $this->assertNotNull($invitation->fresh()->rejection_time);
    }

    private function invitation(Driver $driver, bool $refused = false): OrderInvitation
    {
        return OrderInvitation::create([
            'driver_id' => $driver->id,
            'order_id' => $this->order->id,
            'is_waiting_acceptation' => !$refused,
            'rejection_time' => $refused ? now() : null,
        ]);
    }

    private function refuse(Driver $driver, OrderInvitation $invitation)
    {
        $token = auth('api-drivers')->login($driver);

        return $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->putJson("/api/v1/drivers/orders_invitations/{$invitation->id}/refuse");
    }
}
