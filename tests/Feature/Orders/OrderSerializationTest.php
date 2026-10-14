<?php

namespace Tests\Feature\Orders;

use App\Models\Driver;
use App\Models\Engin;
use App\Models\EnginPicture;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Models\Payment;
use App\Models\RoutePoint;
use App\Models\RoutePointHistory;
use App\Models\Service;
use Database\Seeders\TypeEnginModelsSeeder;
use Database\Seeders\TypeEnginsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Orders\Aggregats\AggregatOrderTestCase;

/**
 * Fige le JSON des commandes renvoyé aux applications mobiles.
 *
 * Les snapshots (tests/Feature/Orders/__snapshots__) sont générés au premier passage ;
 * les identifiants, références et jetons aléatoires sont normalisés.
 */
class OrderSerializationTest extends AggregatOrderTestCase
{
    private const SNAPSHOT_DIR = __DIR__.'/__snapshots__';

    private Driver $driver;

    private Order $gravierOrder;

    private Order $courseOrder;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->seed([TypeEnginsSeeder::class, TypeEnginModelsSeeder::class]);

        $this->driver = Driver::factory()->create([
            'name' => 'Kouassi Chauffeur',
            'first_name' => 'Kouassi',
            'last_name' => 'Chauffeur',
            'phone' => '2250500000000',
            'phone_number' => '0500000000',
            'photo_url' => 'https://example.test/driver.png',
            'current_balance' => 100,
            'old_balance' => 50,
            'last_location_latitude' => 5.36,
            'last_location_longitude' => -3.99,
            'rate' => 4.5,
            'zone_base_id' => $this->zone->id,
        ]);

        $engin = Engin::create([
            'driver_id' => (string) $this->driver->id,
            'immatriculation' => 'AB-123-CI',
            'brand' => 'Renault',
        ]);
        EnginPicture::create(['engin_id' => $engin->id, 'url' => 'https://example.test/engin.png']);

        $this->gravierOrder = Order::findOrFail($this->createOrder([
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
        ])->assertOk()->json('data.id'));

        $this->courseOrder = Order::findOrFail($this->createOrder([
            'service_slug' => Service::COURSE,
            'meta_data' => [
                'type_engin_slug' => 'camion-benne',
                'engin_model' => '6-roues-8m3',
                'delivery_type_code' => 'EXPRESS',
                'delivery_fees' => 20000,
            ],
            'route_points' => [
                $this->destination(['type' => 'source', 'visit_order' => 1]),
                $this->destination(['type' => 'destination', 'visit_order' => 2, 'address_name' => 'Koumassi']),
            ],
        ])->assertOk()->json('data.id'));

        // Commande gravier attribuée au chauffeur, avec un historique de point et un paiement
        $this->gravierOrder->update(['driver_id' => $this->driver->id, 'status' => Order::PERFORMER_FOUND]);
        $routePoint = RoutePoint::where('order_id', $this->gravierOrder->id)->where('type', 'source')->firstOrFail();
        RoutePointHistory::create(['route_point_id' => $routePoint->id, 'latitude' => 5.4, 'longitude' => -4.0, 'status' => 'arrived']);
        Payment::create([
            'invoice_id' => Invoice::where('order_id', $this->gravierOrder->id)->value('id'),
            'payment_method_code' => 'cash',
            'payment_reference' => 'PAY-TEST',
            'amount' => 1000,
            'currency_code' => 'XOF',
            'status' => 'pending',
        ]);

        // La course est proposée au chauffeur
        OrderInvitation::create([
            'order_id' => $this->courseOrder->id,
            'driver_id' => $this->driver->id,
            'is_waiting_acceptation' => true,
        ]);
    }

    public static function endpoints(): array
    {
        return [
            'order_info' => ['order_info'],
            'customer_orders' => ['customer_orders'],
            'driver_orders' => ['driver_orders'],
            'driver_invitations' => ['driver_invitations'],
        ];
    }

    /**
     * @test
     * @dataProvider endpoints
     */
    public function the_order_json_does_not_change(string $endpoint)
    {
        $json = $this->callEndpoint($endpoint)->assertOk()->json('data');

        // Les commandes ont la même date de création (horloge figée) : leur ordre n'est pas déterministe
        if ($endpoint === 'customer_orders') {
            usort($json, fn ($a, $b) => strcmp($a['service_slug'], $b['service_slug']));
        }

        $this->assertMatchesSnapshot($endpoint, $this->normalize($json));
    }

    /** @test */
    public function the_number_of_queries_does_not_grow_with_the_number_of_orders()
    {
        $before = $this->countQueries(fn () => $this->callEndpoint('customer_orders')->assertOk());

        foreach (range(1, 5) as $i) {
            $this->createOrder([
                'service_slug' => Service::COURSE,
                'meta_data' => [
                    'type_engin_slug' => 'camion-benne',
                    'engin_model' => '6-roues-8m3',
                    'delivery_type_code' => 'EXPRESS',
                    'delivery_fees' => 20000,
                ],
                'route_points' => [
                    $this->destination(['type' => 'source', 'visit_order' => 1]),
                    $this->destination(['type' => 'destination', 'visit_order' => 2]),
                ],
            ])->assertOk();
        }

        $after = $this->countQueries(fn () => $this->callEndpoint('customer_orders')->assertOk()->assertJsonCount(7, 'data'));

        $this->assertSame($before, $after);
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function callEndpoint(string $endpoint)
    {
        return match ($endpoint) {
            'order_info' => $this->actingAs($this->customer, 'api-customers')
                ->getJson("/api/v1/orders/{$this->gravierOrder->id}/info"),
            'customer_orders' => $this->actingAs($this->customer, 'api-customers')
                ->getJson('/api/v1/customers/orders/list'),
            'driver_orders' => $this->actingAs($this->driver, 'api-drivers')
                ->getJson('/api/v1/drivers/orders/list'),
            'driver_invitations' => $this->actingAs($this->driver, 'api-drivers')
                ->getJson('/api/v1/drivers/orders_invitations/list'),
        };
    }

    /**
     * Remplace les valeurs qui changent d'une exécution à l'autre (identifiants, références, jetons).
     */
    private function normalize(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $k => $v) {
                $normalized[$k] = $this->normalize($v, is_string($k) ? $k : $key);
            }
            ksort($normalized);

            return $normalized;
        }

        if ($key !== null && $value !== null) {
            if ($key === 'id' || $key === 'laravel_through_key' || str_ends_with($key, '_id')) {
                return '<id>';
            }
            if (in_array($key, ['reference', 'public_token'], true)) {
                return '<random>';
            }
            // Lignes de catalogue insérées par les seeders avec l'horloge réelle
            if (in_array($key, ['created_at', 'updated_at'], true) && !str_starts_with((string) $value, '2026-09-22')) {
                return '<seeded_at>';
            }
        }

        return $value;
    }

    private function assertMatchesSnapshot(string $name, array $data): void
    {
        $path = self::SNAPSHOT_DIR."/{$name}.json";
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";

        if (!file_exists($path)) {
            @mkdir(self::SNAPSHOT_DIR, 0755, true);
            file_put_contents($path, $json);
            $this->markTestIncomplete("Snapshot {$name} créé.");
        }

        $this->assertSame(file_get_contents($path), $json);
    }
}
