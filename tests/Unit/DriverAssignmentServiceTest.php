<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\DriverAssignmentService;
use App\Services\DriverLocationAssignmentService;
use App\Services\DriverExpressAssignmentService;
use App\Services\DriverEnjourneeAssignmentService;
use App\Services\DriverEnSemaineAssignmentService;
use App\Services\DriverNuitAssignmentService;
use App\Models\Order;
use App\Models\DeliveryType;
use App\Models\Service;
use Illuminate\Support\Facades\Log;

class DriverAssignmentServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    // =========================================================================
    // LOCATION
    // =========================================================================

    /** @test */
    public function it_delegates_to_location_service_when_order_is_location()
    {
        $order = Order::factory()->make(['is_location' => true]);

        $this->mock(DriverLocationAssignmentService::class)
            ->shouldReceive('findEligibleDriversForLocation')
            ->once()
            ->with($order, 5)
            ->andReturn([]);

        (new DriverAssignmentService())->sendInvitations($order, 10);
    }

    /** @test */
    public function it_does_not_call_other_services_when_order_is_location()
    {
        $order = Order::factory()->make(['is_location' => true]);

        $this->mock(DriverLocationAssignmentService::class)
            ->shouldReceive('findEligibleDriversForLocation')
            ->once()
            ->andReturn([]);

        $this->mock(DriverExpressAssignmentService::class)
            ->shouldNotReceive('assignCourseNearestDrivers')
            ->shouldNotReceive('assignAggregatNearestDrivers');

        (new DriverAssignmentService())->sendInvitations($order, 10);
    }

    // =========================================================================
    // EXPRESS
    // =========================================================================

    /** @test */
    public function it_delegates_to_express_service_for_course()
    {
        $order = Order::factory()->make([
            'is_location'        => false,
            'delivery_type_code' => DeliveryType::TYPE_EXPRESS,
            'service_slug'       => Service::COURSE,
        ]);

        $this->mock(DriverExpressAssignmentService::class)
            ->shouldReceive('assignCourseNearestDrivers')
            ->once()
            ->with($order, 10, 5);

        (new DriverAssignmentService())->sendInvitations($order, 10);
    }

    /** @test */
    public function it_delegates_to_express_service_for_aggregat()
    {
        $order = Order::factory()->make([
            'is_location'        => false,
            'delivery_type_code' => DeliveryType::TYPE_EXPRESS,
            'service_slug'       => Service::AGREGATS_CONSTRUCTION,
        ]);

        $this->mock(DriverExpressAssignmentService::class)
            ->shouldReceive('assignAggregatNearestDrivers')
            ->once()
            ->with($order, 10, 5);

        (new DriverAssignmentService())->sendInvitations($order, 10);
    }

    // =========================================================================
    // EN JOURNEE
    // =========================================================================

    /** @test */
    public function it_delegates_to_enjournee_service_for_course()
    {
        $order = Order::factory()->make([
            'is_location'        => false,
            'delivery_type_code' => DeliveryType::TYPE_EN_JOURNEE,
            'service_slug'       => Service::COURSE,
        ]);

        $this->mock(DriverEnjourneeAssignmentService::class)
            ->shouldReceive('assignCourseNearestDrivers')
            ->once()
            ->with($order, 10, 5);

        (new DriverAssignmentService())->sendInvitations($order, 10);
    }

    /** @test */
    public function it_delegates_to_enjournee_service_for_aggregat()
    {
        $order = Order::factory()->make([
            'is_location'        => false,
            'delivery_type_code' => DeliveryType::TYPE_EN_JOURNEE,
            'service_slug'       => Service::AGREGATS_CONSTRUCTION,
        ]);

        $this->mock(DriverEnjourneeAssignmentService::class)
            ->shouldReceive('assignAggregatNearestDrivers')
            ->once()
            ->with($order, 10, 5);

        (new DriverAssignmentService())->sendInvitations($order, 10);
    }

    // =========================================================================
    // EN SEMAINE
    // =========================================================================

    /** @test */
    public function it_delegates_to_ensemaine_service_for_course()
    {
        $order = Order::factory()->make([
            'is_location'        => false,
            'delivery_type_code' => DeliveryType::TYPE_DE_SEMAINE,
            'service_slug'       => Service::COURSE,
        ]);

        $this->mock(DriverEnSemaineAssignmentService::class)
            ->shouldReceive('assignCourseNearestDrivers')
            ->once()
            ->with($order, 10, 5);

        (new DriverAssignmentService())->sendInvitations($order, 10);
    }

    /** @test */
    public function it_delegates_to_ensemaine_service_for_aggregat()
    {
        $order = Order::factory()->make([
            'is_location'        => false,
            'delivery_type_code' => DeliveryType::TYPE_DE_SEMAINE,
            'service_slug'       => Service::AGREGATS_CONSTRUCTION,
        ]);

        $this->mock(DriverEnSemaineAssignmentService::class)
            ->shouldReceive('assignAggregatNearestDrivers')
            ->once()
            ->with($order, 10, 5);

        (new DriverAssignmentService())->sendInvitations($order, 10);
    }

    // =========================================================================
    // DE NUIT
    // =========================================================================

    /** @test */
    public function it_delegates_to_nuit_service_for_course_after_20h()
    {
        $this->travelTo(now()->setHour(21));

        $order = Order::factory()->make([
            'is_location'        => false,
            'delivery_type_code' => DeliveryType::TYPE_DE_NUIT,
            'service_slug'       => Service::COURSE,
        ]);

        $this->mock(DriverNuitAssignmentService::class)
            ->shouldReceive('assignCourseNearestDrivers')
            ->once()
            ->with($order, 10, 5);

        (new DriverAssignmentService())->sendInvitations($order, 10);
    }

    /** @test */
    public function it_delegates_to_nuit_service_for_aggregat_after_20h()
    {
        $this->travelTo(now()->setHour(22));

        $order = Order::factory()->make([
            'is_location'        => false,
            'delivery_type_code' => DeliveryType::TYPE_DE_NUIT,
            'service_slug'       => Service::AGREGATS_CONSTRUCTION,
        ]);

        $this->mock(DriverNuitAssignmentService::class)
            ->shouldReceive('assignAggregatNearestDrivers')
            ->once()
            ->with($order, 10, 5);

        (new DriverAssignmentService())->sendInvitations($order, 10);
    }

    /** @test */
    public function it_skips_nuit_service_before_20h()
    {
        $this->travelTo(now()->setHour(15));

        $order = Order::factory()->make([
            'is_location'        => false,
            'delivery_type_code' => DeliveryType::TYPE_DE_NUIT,
            'service_slug'       => Service::COURSE,
        ]);

        $this->mock(DriverNuitAssignmentService::class)
            ->shouldNotReceive('assignCourseNearestDrivers')
            ->shouldNotReceive('assignAggregatNearestDrivers');

        (new DriverAssignmentService())->sendInvitations($order, 10);
    }
}
