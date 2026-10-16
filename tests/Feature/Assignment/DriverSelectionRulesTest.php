<?php

namespace Tests\Feature\Assignment;

use App\Models\Carrier;
use App\Models\DeliveryType;
use App\Models\Driver;
use App\Models\DriverCarrier;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RoutePoint;
use App\Models\Service;
use App\Services\DriverEnjourneeAssignmentService;
use App\Services\DriverEnSemaineAssignmentService;
use App\Services\DriverExpressAssignmentService;
use App\Services\DriverNuitAssignmentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Fige les chauffeurs retenus par chaque service d'attribution, selon leur situation et le moment de la recherche.
 * Le snapshot (__snapshots__/driver_selection.json) est généré au premier passage.
 */
class DriverSelectionRulesTest extends TestCase
{
    private const SNAPSHOT = __DIR__.'/__snapshots__/driver_selection.json';

    private const PICKUP_LATITUDE = 5.36;
    private const PICKUP_LONGITUDE = -3.99;

    private const SERVICES = [
        'express' => DriverExpressAssignmentService::class,
        'en-journee' => DriverEnjourneeAssignmentService::class,
        'en-semaine' => DriverEnSemaineAssignmentService::class,
        'de-nuit' => DriverNuitAssignmentService::class,
    ];

    private const MOMENTS = [
        'mardi 10h' => '2026-09-22 10:00:00',
        'mardi 15h (après la limite journée)' => '2026-09-22 15:00:00',
        'mardi 02h' => '2026-09-22 02:00:00',
        'vendredi 10h' => '2026-09-25 10:00:00',
        'samedi 10h' => '2026-09-26 10:00:00',
    ];

    private Order $order;

    private Carrier $carrier;

    /** @test */
    public function the_selected_drivers_do_not_change()
    {
        Event::fake();

        $results = [];

        foreach (self::MOMENTS as $moment => $at) {
            $this->travelTo(Carbon::parse($at));
            $this->createWorld();

            foreach (self::SERVICES as $name => $class) {
                $results['course'][$name][$moment] = $this->courseSelection(app($class));
                $results['agregat'][$name][$moment] = $this->aggregatSelection(app($class));
            }

            $this->resetWorld();
        }

        $json = json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

        if (!file_exists(self::SNAPSHOT)) {
            @mkdir(dirname(self::SNAPSHOT), 0755, true);
            file_put_contents(self::SNAPSHOT, $json);
            $this->markTestIncomplete('Snapshot driver_selection créé.');
        }

        $this->assertSame(file_get_contents(self::SNAPSHOT), $json);
    }

    private function courseSelection($service): array
    {
        $this->refreshPresence();

        return $service->findCourseNearestDrivers(Service::COURSE, self::PICKUP_LATITUDE, self::PICKUP_LONGITUDE, 50, 10, $this->order->id)
            ->pluck('name')->sort()->values()->all();
    }

    private function aggregatSelection($service): array
    {
        $this->refreshPresence();

        try {
            $ranking = $service->findAggregatNearestDrivers($this->carrier->id, $this->order->id, Service::AGREGATS_CONSTRUCTION, 50, 10);
        } catch (\Exception $e) {
            return ['exception' => $e->getMessage()];
        }

        // Le classement (AggregatDriverScoringService, testé à part) départage des ex aequo dans un ordre
        // non déterministe ici : seuls les chauffeurs retenus sont comparés
        return Driver::whereIn('id', array_column($ranking, 'driver_id'))->pluck('name')->sort()->values()->all();
    }

    /**
     * La recherche compare updated_at à NOW() côté base, qui ignore l'horloge figée de Laravel.
     */
    private function refreshPresence(): void
    {
        DB::table('drivers')->where('name', '!=', 'hors ligne')->update(['updated_at' => DB::raw('NOW()')]);
        DB::table('drivers')->where('name', 'hors ligne')->update(['updated_at' => DB::raw("NOW() - INTERVAL '2 hours'")]);
    }

    private function createWorld(): void
    {
        $this->carrier = Carrier::create([
            'name' => 'Carrière',
            'location_latitude' => 5.37,
            'location_longitude' => -4.00,
            'location' => ['latitude' => 5.37, 'longitude' => -4.00],
        ]);

        $this->order = Order::create([
            'reference' => Order::generateReference(),
            'status' => Order::PERFORMER_LOOKUP,
            'currency_code' => 'XOF',
            'is_draft' => false,
        ]);
        OrderItem::create([
            'order_id' => $this->order->id,
            'service_slug' => Service::AGREGATS_CONSTRUCTION,
            'meta_data' => ['product_slug' => Product::GRAVIER_SLUG],
            'carrier_id' => $this->carrier->id,
        ]);
        RoutePoint::create([
            'order_id' => $this->order->id,
            'type' => 'source',
            'latitude' => self::PICKUP_LATITUDE,
            'longitude' => self::PICKUP_LONGITUDE,
        ]);

        $today = now()->toDateString();

        $this->driver('libre');
        $this->driver('loin', latitude: 5.80);
        $this->driver('indisponible', attributes: ['is_available' => false]);
        $this->driver('hors ligne');
        $this->driver('sans le service', attributes: ['services' => ['delivery']]);

        $this->ordersFor($this->driver('course démarrée'), 1, DeliveryType::TYPE_EN_JOURNEE, ['is_started' => true]);
        $this->ordersFor($this->driver('express en attente'), 1, DeliveryType::TYPE_EXPRESS);
        $this->ordersFor($this->driver('1 journée'), 1, DeliveryType::TYPE_EN_JOURNEE);
        $this->ordersFor($this->driver('3 journées'), 3, DeliveryType::TYPE_EN_JOURNEE);
        $this->ordersFor($this->driver('1 semaine'), 1, DeliveryType::TYPE_DE_SEMAINE);
        $this->ordersFor($this->driver('5 semaines'), 5, DeliveryType::TYPE_DE_SEMAINE);
        $this->ordersFor($this->driver('3 nuits'), 3, DeliveryType::TYPE_DE_NUIT);
        $this->ordersFor($this->driver('journée terminée'), 3, DeliveryType::TYPE_EN_JOURNEE, ['is_completed' => true]);

        $this->rentalFor($this->driver('location en cours'), $today, now()->addDays(2)->toDateString());
        $this->rentalFor($this->driver('location finit aujourd\'hui'), now()->subDays(2)->toDateString(), $today);
        $this->rentalFor($this->driver('location annulée'), $today, now()->addDays(2)->toDateString(), Order::CANCELLED);

        OrderInvitation::create([
            'order_id' => $this->order->id,
            'driver_id' => $this->driver('a refusé')->id,
            'is_waiting_acceptation' => false,
            'rejection_time' => now(),
        ]);
    }

    private function resetWorld(): void
    {
        foreach (['order_invitations', 'order_items', 'route_points', 'orders', 'driver_carriers', 'drivers', 'carriers'] as $table) {
            DB::table($table)->delete();
        }
    }

    private function driver(string $name, float $latitude = 5.365, array $attributes = []): Driver
    {
        $driver = Driver::factory()->create(array_merge([
            'name' => $name,
            'phone' => '225'.crc32($name),
            'is_available' => true,
            'is_active' => true,
            'services' => [Service::COURSE, Service::AGREGATS_CONSTRUCTION],
            'current_balance' => 100,
            'rate' => 4,
            'last_location_latitude' => $latitude,
            'last_location_longitude' => -3.99,
            'last_location' => ['latitude' => $latitude, 'longitude' => -3.99],
        ], $attributes));

        DriverCarrier::create(['driver_id' => $driver->id, 'carrier_id' => $this->carrier->id]);

        return $driver;
    }

    private function ordersFor(Driver $driver, int $count, string $deliveryType, array $attributes = []): void
    {
        foreach (range(1, $count) as $i) {
            Order::create(array_merge([
                'reference' => Order::generateReference(),
                'driver_id' => $driver->id,
                'status' => Order::PERFORMER_FOUND,
                'currency_code' => 'XOF',
                'delivery_type_code' => $deliveryType,
                'is_draft' => false,
                'is_completed' => false,
                'is_started' => false,
                'is_running' => false,
            ], $attributes));
        }
    }

    private function rentalFor(Driver $driver, string $start, string $end, string $status = Order::PERFORMER_FOUND): void
    {
        $order = Order::create([
            'reference' => Order::generateReference(),
            'driver_id' => $driver->id,
            'status' => $status,
            'currency_code' => 'XOF',
            'is_location' => true,
            'is_draft' => false,
            'is_completed' => false,
            'is_started' => false,
            'is_running' => false,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'service_slug' => Service::LOCATION,
            'location_start_date' => $start,
            'location_end_date' => $end,
        ]);
    }
}
