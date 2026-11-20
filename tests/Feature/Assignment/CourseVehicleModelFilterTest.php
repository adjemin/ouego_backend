<?php

namespace Tests\Feature\Assignment;

use App\Models\Driver;
use App\Models\Engin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RoutePoint;
use App\Models\Service;
use App\Models\TypeEnginModel;
use App\Services\DriverEnjourneeAssignmentService;
use App\Services\DriverEnSemaineAssignmentService;
use App\Services\DriverExpressAssignmentService;
use App\Services\DriverNuitAssignmentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Courses : seuls les chauffeurs ayant un camion du modèle choisi par le client sont invités.
 */
class CourseVehicleModelFilterTest extends TestCase
{
    private const SERVICES = [
        DriverExpressAssignmentService::class,
        DriverEnjourneeAssignmentService::class,
        DriverEnSemaineAssignmentService::class,
        DriverNuitAssignmentService::class,
    ];

    private const LATITUDE = 5.36;
    private const LONGITUDE = -3.99;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        Carbon::setTestNow('2026-09-22 10:00:00'); // mardi

        TypeEnginModel::create(['type_engin_slug' => 'camion-benne', 'title' => '6 roues (8m3)', 'slug' => '6-roues-8m3']);
        TypeEnginModel::create(['type_engin_slug' => 'camion-benne', 'title' => '10 roues (12m3)', 'slug' => '10-roues-12m3']);
        TypeEnginModel::create(['type_engin_slug' => 'camion-plateau-standard', 'title' => '4 roues', 'slug' => '4-roues']);

        $this->driver('benne 6 roues', [['camion-benne', '6-roues-8m3']]);
        $this->driver('benne 10 roues', [['camion-benne', '10-roues-12m3']]);
        $this->driver('plateau 4 roues', [['camion-plateau-standard', '4-roues']]);
        $this->driver('benne sans modèle', [['camion-benne', null]]);
        $this->driver('plateau sans modèle', [['camion-plateau-standard', null]]);
        $this->driver('sans camion', []);
        $this->driver('camion vide', [[null, null]]);
        $this->driver('benne 6 roues supprimée', [['camion-benne', '6-roues-8m3']], softDeleteTrucks: true);
        $this->driver('plateau et benne 6 roues', [['camion-plateau-standard', '4-roues'], ['camion-benne', '6-roues-8m3']]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @test */
    public function only_drivers_with_the_chosen_model_or_an_unspecified_model_of_the_same_type_are_selected()
    {
        $order = $this->courseOrder(['type_engin_slug' => 'camion-benne', 'engin_model' => '6-roues-8m3']);

        foreach (self::SERVICES as $service) {
            $this->assertSame(
                ['benne 6 roues', 'benne sans modèle', 'plateau et benne 6 roues'],
                $this->selection($service, $order),
                $service
            );
        }
    }

    /** @test */
    public function the_type_of_the_model_prevails_over_the_type_sent_by_the_client()
    {
        $order = $this->courseOrder(['type_engin_slug' => 'camion-benne', 'engin_model' => '4-roues']);

        $this->assertSame(
            ['plateau 4 roues', 'plateau et benne 6 roues', 'plateau sans modèle'],
            $this->selection(DriverExpressAssignmentService::class, $order)
        );
    }

    /** @test */
    public function without_model_nor_type_no_filter_is_applied()
    {
        $order = $this->courseOrder([]);

        $this->assertCount(9, $this->selection(DriverExpressAssignmentService::class, $order));
    }

    /** @test */
    public function invitations_are_only_sent_to_matching_drivers()
    {
        $order = $this->courseOrder(['type_engin_slug' => 'camion-benne', 'engin_model' => '10-roues-12m3']);
        $this->refreshPresence();

        app(DriverExpressAssignmentService::class)->assignCourseNearestDrivers($order, 10, 5);

        $invited = Driver::whereIn('id', $order->orderInvitations()->pluck('driver_id'))->pluck('name')->sort()->values()->all();
        $this->assertSame(['benne 10 roues', 'benne sans modèle'], $invited);
    }

    private function selection(string $service, Order $order): array
    {
        $this->refreshPresence();

        return app($service)
            ->findCourseNearestDrivers(Service::COURSE, self::LATITUDE, self::LONGITUDE, 50, 10, $order->id)
            ->pluck('name')->sort()->values()->all();
    }

    /**
     * La recherche compare updated_at à NOW() côté base, qui ignore l'horloge figée de Laravel.
     */
    private function refreshPresence(): void
    {
        DB::table('drivers')->update(['updated_at' => DB::raw('NOW()')]);
    }

    private function courseOrder(array $metaData): Order
    {
        $order = Order::create([
            'reference' => Order::generateReference(),
            'status' => Order::PERFORMER_LOOKUP,
            'service_slug' => Service::COURSE,
            'currency_code' => 'XOF',
            'is_draft' => false,
        ]);
        OrderItem::create(['order_id' => $order->id, 'service_slug' => Service::COURSE, 'meta_data' => $metaData]);
        RoutePoint::create(['order_id' => $order->id, 'type' => 'source', 'latitude' => self::LATITUDE, 'longitude' => self::LONGITUDE]);

        return $order;
    }

    /**
     * @param array<array{0: ?string, 1: ?string}> $trucks [type_engin, type_engin_model_slug]
     */
    private function driver(string $name, array $trucks, bool $softDeleteTrucks = false): Driver
    {
        $driver = Driver::factory()->create([
            'name' => $name,
            'phone' => '225'.crc32($name),
            'is_available' => true,
            'is_active' => true,
            'services' => [Service::COURSE],
            'last_location_latitude' => 5.365,
            'last_location_longitude' => self::LONGITUDE,
            'last_location' => ['latitude' => 5.365, 'longitude' => self::LONGITUDE],
        ]);

        foreach ($trucks as [$type, $model]) {
            $engin = Engin::create(['driver_id' => $driver->id, 'type_engin' => $type, 'type_engin_model_slug' => $model]);
            if ($softDeleteTrucks) {
                $engin->delete();
            }
        }

        return $driver;
    }
}
