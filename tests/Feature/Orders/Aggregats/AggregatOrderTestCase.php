<?php

namespace Tests\Feature\Orders\Aggregats;

use App\Models\Carrier;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Setting;
use App\Models\Zone;
use App\Models\ZoneMapping;
use Database\Seeders\DeliveryTypesSeeder;
use Database\Seeders\ProductsSeeder;
use Database\Seeders\ProductTypesSeeder;
use Database\Seeders\ServicesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Socle commun des parcours de commande agrégats (sable, gravier, ciment).
 *
 * Monde de test :
 *  - une zone carrée couvrant Abidjan, reliée à une carrière qui vend tous les produits ;
 *  - Google Distance Matrix simulé : la carrière est toujours à 12,3 km du client ;
 *  - horloge figée un mardi à 10h00 (hors plages interdites de l'option EXPRESS).
 */
abstract class AggregatOrderTestCase extends TestCase
{
    /** Point de livraison du client, à l'intérieur de la zone. */
    protected const DESTINATION_LATITUDE = 5.36;
    protected const DESTINATION_LONGITUDE = -3.99;

    /** Distance simulée entre la carrière et le client. */
    protected const DISTANCE_METERS = 12300;

    protected Customer $customer;

    protected Carrier $carrier;

    protected Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            ServicesSeeder::class,
            DeliveryTypesSeeder::class,
            SettingsSeeder::class,
            ProductsSeeder::class,
            ProductTypesSeeder::class,
        ]);

        $this->seedCiment();

        $this->travelTo(Carbon::parse('2026-09-22 10:00:00'));

        Http::preventStrayRequests();
        Http::fake([
            'maps.googleapis.com/maps/api/distancematrix/*' => Http::response([
                'rows' => [[
                    'elements' => [[
                        'distance' => ['text' => '12.3 km', 'value' => self::DISTANCE_METERS],
                        'duration' => ['text' => '25 mins', 'value' => 1500],
                        'status' => 'OK',
                    ]],
                ]],
                'status' => 'OK',
            ]),
        ]);

        $this->zone = $this->createZone('Abidjan test', -4.10, 5.30, -3.90, 5.45);

        $this->carrier = $this->createCarrier([
            'gravier-515-petit-grain',
            'sable-gros-grain',
            'ciment-portland',
        ]);

        $this->customer = Customer::create([
            'name' => 'Client Test',
            'phone' => '2250700000000',
            'is_active' => true,
        ]);
    }

    /**
     * Le ciment n'existe pas dans les seeders et ses settings y valent 0 :
     * on le crée avec des valeurs non nulles pour vérifier réellement les calculs.
     */
    private function seedCiment(): void
    {
        $ciment = Product::create(['name' => 'Ciment', 'slug' => Product::CIMENT_SLUG]);

        ProductType::create([
            'product_id' => $ciment->id,
            'name' => 'Ciment Portland',
            'slug' => 'ciment-portland',
            'price' => 5000,
            'currency_code' => 'XOF',
        ]);

        foreach ([
            'CIMENT_DISTANCE_DE_BASE' => '10',
            'CIMENT_QUANTITE_DE_BASE' => '10',
            'CIMENT_PRIX_DE_BASE' => '30000',
            'CIMENT_PRIX_KILOMETRE' => '500',
            'CIMENT_PRIX_TONNAGE' => '200',
            'CIMENT_FRAIS_DE_ROUTE' => '2000',
            'CIMENT_COMMISSION_OUEGO' => '3000',
            'CIMENT_COMMISSION_OUEGO_MIN' => '3000',
        ] as $name => $value) {
            Setting::updateOrCreate(['name' => $name], ['value' => $value]);
        }
    }

    protected function createZone(string $name, float $minLng, float $minLat, float $maxLng, float $maxLat): Zone
    {
        $polygon = sprintf(
            'POLYGON((%1$s %2$s, %3$s %2$s, %3$s %4$s, %1$s %4$s, %1$s %2$s))',
            $minLng, $minLat, $maxLng, $maxLat
        );

        DB::statement(
            'INSERT INTO zones (name, geometry, created_at, updated_at) VALUES (?, ST_GeomFromText(?, 4326), now(), now())',
            [$name, $polygon]
        );

        return Zone::where('name', $name)->firstOrFail();
    }

    /**
     * @param  string[]  $productTypeSlugs
     */
    protected function createCarrier(array $productTypeSlugs, ?Zone $zone = null, bool $isActive = true): Carrier
    {
        $carrier = Carrier::create([
            'name' => 'Carrière Test',
            'location_latitude' => 5.40,
            'location_longitude' => -4.00,
            'is_active' => $isActive,
            'products' => $productTypeSlugs,
            'location' => ['latitude' => 5.40, 'longitude' => -4.00],
        ]);

        ZoneMapping::create([
            'zone_id' => ($zone ?? $this->zone)->id,
            'carrier_id' => $carrier->id,
        ]);

        return $carrier;
    }

    protected function destination(array $overrides = []): array
    {
        return array_merge([
            'address_name' => "Cocody, Abidjan, Côte d'ivoire",
            'latitude' => self::DESTINATION_LATITUDE,
            'longitude' => self::DESTINATION_LONGITUDE,
            'type' => 'destination',
            'parcel_details' => '',
            'contact_fullname' => 'Client Test',
            'contact_phone' => '2250700000000',
        ], $overrides);
    }

    protected function estimate(string $product, array $payload): TestResponse
    {
        return $this->actingAs($this->customer, 'api-customers')
            ->postJson("/api/v1/orders/delivery/{$product}/estimate_price", $payload);
    }

    protected function createOrder(array $item, string $paymentMethod = 'cash'): TestResponse
    {
        return $this->actingAs($this->customer, 'api-customers')
            ->postJson('/api/v1/orders/create', [
                'payment_method_code' => $paymentMethod,
                'items' => [$item],
            ]);
    }
}
