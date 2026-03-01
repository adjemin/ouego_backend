<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\DriverLocationAssignmentService;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Events\OrderAssigned;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;

class DriverLocationAssignmentServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    // =========================================================================
    // Cas de retour anticipé (early returns) — pas de PostGIS nécessaire
    // =========================================================================

    /** @test */
    public function it_returns_empty_when_no_location_order_item()
    {
        $order = $this->mockOrder(item: null);

        $result = (new DriverLocationAssignmentService())->findEligibleDriversForLocation($order);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_returns_empty_when_start_date_is_missing()
    {
        $order = $this->mockOrder(
            item: $this->mockItem(startDate: null, endDate: '2026-04-01')
        );

        $result = (new DriverLocationAssignmentService())->findEligibleDriversForLocation($order);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_returns_empty_when_end_date_is_missing()
    {
        $order = $this->mockOrder(
            item: $this->mockItem(startDate: '2026-03-10', endDate: null)
        );

        $result = (new DriverLocationAssignmentService())->findEligibleDriversForLocation($order);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_returns_empty_when_no_route_point_found()
    {
        // order_id = 0 : aucun RoutePoint en base → retour anticipé
        $order = $this->mockOrder(
            item: $this->mockItem(
                startDate: now()->addDay()->toDateString(),
                endDate:   now()->addDays(3)->toDateString(),
            ),
            orderId: 0,
        );

        $result = (new DriverLocationAssignmentService())->findEligibleDriversForLocation($order);

        $this->assertEmpty($result);
    }

    // =========================================================================
    // Création d'invitations & dispatch d'événements
    // =========================================================================

    /** @test */
    public function it_creates_invitations_and_dispatches_event_for_each_eligible_driver()
    {
        Event::fake();

        $drivers = collect([
            (object) ['id' => 1],
            (object) ['id' => 2],
        ]);

        $invitation = Mockery::mock(OrderInvitation::class)->makePartial();

        // Partial mock du service : on bypasse queryEligibleDrivers (requête PostGIS)
        $service = Mockery::mock(DriverLocationAssignmentService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('queryEligibleDrivers')->andReturn($drivers);

        // Stub de OrderInvitation::firstOrCreate
        Mockery::mock('alias:' . OrderInvitation::class)
            ->shouldReceive('firstOrCreate')
            ->twice()
            ->andReturn($invitation);

        $order = $this->mockOrder(
            item: $this->mockItem(
                startDate: now()->addDay()->toDateString(),
                endDate:   now()->addDays(3)->toDateString(),
            ),
        );

        $result = $service->findEligibleDriversForLocation($order, 5);

        Event::assertDispatched(OrderAssigned::class, 2);
        $this->assertEquals([1, 2], $result);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Crée un stub d'Order dont la relation orderItems() retourne $item.
     */
    private function mockOrder(?object $item, int $orderId = 99): Order
    {
        $itemsQuery = Mockery::mock();
        $itemsQuery->shouldReceive('where')->with('service_slug', 'location')->andReturnSelf();
        $itemsQuery->shouldReceive('first')->andReturn($item);

        /** @var Order $order */
        $order = Mockery::mock(Order::class)->makePartial();
        $order->id           = $orderId;
        $order->service_slug = 'location';
        $order->shouldReceive('orderItems')->andReturn($itemsQuery);

        return $order;
    }

    /**
     * Crée un stub d'OrderItem avec les dates de location.
     */
    private function mockItem(?string $startDate, ?string $endDate): object
    {
        $item                      = Mockery::mock();
        $item->location_start_date = $startDate;
        $item->location_end_date   = $endDate;

        return $item;
    }
}
