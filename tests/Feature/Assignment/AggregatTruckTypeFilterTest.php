<?php

namespace Tests\Feature\Assignment;

use App\Models\Carrier;
use App\Models\Driver;
use App\Models\DriverCarrier;
use App\Models\Engin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RoutePoint;
use App\Models\Service;
use App\Models\TypeEngin;
use App\Services\DriverEnjourneeAssignmentService;
use App\Services\DriverEnSemaineAssignmentService;
use App\Services\DriverExpressAssignmentService;
use App\Services\DriverNuitAssignmentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Commandes d'agrégats : seuls les chauffeurs ayant un camion d'un type d'engin
 * associé au produit commandé sont retenus.
 */
class AggregatTruckTypeFilterTest extends TestCase
{
    private const SERVICES = [
        DriverExpressAssignmentService::class,
        DriverEnjourneeAssignmentService::class,
        DriverEnSemaineAssignmentService::class,
        DriverNuitAssignmentService::class,
    ];

    private Carrier $carrier;

    private Order $order;

    private Product $gravier;

    private TypeEngin $benne;

    private TypeEngin $plateau;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        Carbon::setTestNow('2026-09-22 10:00:00'); // mardi

        $this->gravier = Product::create(['name' => 'Gravier', 'slug' => Product::GRAVIER_SLUG]);
        $this->benne = TypeEngin::create(['name' => 'Camion benne', 'slug' => 'camion-benne']);
        $this->plateau = TypeEngin::create(['name' => 'Camion plateau', 'slug' => 'camion-plateau']);

        $this->carrier = Carrier::create([
            'name' => 'Carrière',
            'location_latitude' => 5.37,
            'location_longitude' => -4.00,
            'location' => ['latitude' => 5.37, 'longitude' => -4.00],
        ]);

        $this->order = Order::create([
            'reference' => Order::generateReference(),
            'status' => Order::PERFORMER_LOOKUP,
            'service_slug' => Service::AGREGATS_CONSTRUCTION,
            'currency_code' => 'XOF',
            'is_draft' => false,
        ]);
        OrderItem::create([
            'order_id' => $this->order->id,
            'service_slug' => Service::AGREGATS_CONSTRUCTION,
            'meta_data' => ['product_slug' => Product::GRAVIER_SLUG],
            'carrier_id' => $this->carrier->id,
        ]);
        RoutePoint::create(['order_id' => $this->order->id, 'type' => 'source', 'latitude' => 5.36, 'longitude' => -3.99]);

        $this->driver('benne', ['camion-benne']);
        $this->driver('plateau', ['camion-plateau']);
        $this->driver('benne et plateau', ['camion-plateau', 'camion-benne']);
        $this->driver('sans camion', []);
        $this->driver('benne supprimée', ['camion-benne'], softDeleteTrucks: true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @test */
    public function only_drivers_with_a_truck_type_linked_to_the_product_are_selected()
    {
        $this->gravier->typeEngins()->sync([$this->benne->id]);

        foreach (self::SERVICES as $service) {
            $this->assertSame(['benne', 'benne et plateau'], $this->selection($service), $service);
        }
    }

    /** @test */
    public function several_linked_truck_types_are_all_accepted()
    {
        $this->gravier->typeEngins()->sync([$this->benne->id, $this->plateau->id]);

        $this->assertSame(['benne', 'benne et plateau', 'plateau'], $this->selection(DriverExpressAssignmentService::class));
    }

    /** @test */
    public function no_filter_is_applied_when_the_product_has_no_linked_truck_type()
    {
        $this->assertSame(
            ['benne', 'benne et plateau', 'benne supprimée', 'plateau', 'sans camion'],
            $this->selection(DriverExpressAssignmentService::class)
        );
    }

    /** @test */
    public function invitations_are_only_sent_to_matching_drivers()
    {
        $this->gravier->typeEngins()->sync([$this->plateau->id]);
        $this->refreshPresence();
        app(DriverExpressAssignmentService::class)->assignAggregatNearestDrivers($this->order->fresh(), 10, 5);

        $invited = Driver::whereIn('id', $this->order->orderInvitations()->pluck('driver_id'))->pluck('name')->sort()->values()->all();
        $this->assertSame(['benne et plateau', 'plateau'], $invited);
    }

    private function selection(string $service): array
    {
        $this->refreshPresence();

        try {
            $ranking = app($service)->findAggregatNearestDrivers($this->carrier->id, $this->order->id, Service::AGREGATS_CONSTRUCTION, 50, 10);
        } catch (\Exception $e) {
            return [];
        }

        return Driver::whereIn('id', array_column($ranking, 'driver_id'))->pluck('name')->sort()->values()->all();
    }

    /**
     * La recherche compare updated_at à NOW() côté base, qui ignore l'horloge figée de Laravel.
     */
    private function refreshPresence(): void
    {
        DB::table('drivers')->update(['updated_at' => DB::raw('NOW()')]);
    }

    private function driver(string $name, array $truckTypes, bool $softDeleteTrucks = false): Driver
    {
        $driver = Driver::factory()->create([
            'name' => $name,
            'phone' => '225'.crc32($name),
            'is_available' => true,
            'is_active' => true,
            'services' => [Service::AGREGATS_CONSTRUCTION],
            'current_balance' => 100,
            'rate' => 4,
            'last_location_latitude' => 5.365,
            'last_location_longitude' => -3.99,
            'last_location' => ['latitude' => 5.365, 'longitude' => -3.99],
        ]);

        DriverCarrier::create(['driver_id' => $driver->id, 'carrier_id' => $this->carrier->id]);

        foreach ($truckTypes as $type) {
            $engin = Engin::create(['driver_id' => $driver->id, 'type_engin' => $type]);
            if ($softDeleteTrucks) {
                $engin->delete();
            }
        }

        return $driver;
    }
}
