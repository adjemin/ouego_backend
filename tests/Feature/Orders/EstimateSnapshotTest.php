<?php

namespace Tests\Feature\Orders;

use App\Models\Service;
use Database\Seeders\TypeEnginModelsSeeder;
use Database\Seeders\TypeEnginsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Orders\Aggregats\AggregatOrderTestCase;

/**
 * Fige les estimations de prix et les refus de commande selon le type de livraison et l'heure.
 * Le snapshot (__snapshots__/estimates.json) est généré au premier passage.
 */
class EstimateSnapshotTest extends AggregatOrderTestCase
{
    private const SNAPSHOT = __DIR__.'/__snapshots__/estimates.json';

    private const MOMENTS = [
        'mardi 08h (express fermé)' => '2026-09-22 08:00:00',
        'mardi 10h' => '2026-09-22 10:00:00',
        'mardi 15h (journée fermée)' => '2026-09-22 15:00:00',
        'mardi 20h (nuit fermée)' => '2026-09-22 20:00:00',
    ];

    private const DELIVERY_TYPES = ['EXPRESS', 'en-journee', 'de-nuit', 'en-semaine'];

    private const PRODUCTS = [
        'gravier' => ['gravier-515-petit-grain', 25],
        'ciment' => ['ciment-portland', 50],
        'sable' => ['sable-gros-grain', 1],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        $this->seed([TypeEnginsSeeder::class, TypeEnginModelsSeeder::class]);
    }

    /** @test */
    public function the_estimates_and_refusals_do_not_change()
    {
        $results = [];

        foreach (self::MOMENTS as $moment => $at) {
            $this->travelTo(Carbon::parse($at));

            foreach ([0, 1, 2] as $stops) {
                $results['course'][$moment]["{$stops} arrêt(s)"] = $this->rideEstimate($stops);
            }

            foreach (self::PRODUCTS as $product => [$productTypeSlug, $quantity]) {
                foreach (self::DELIVERY_TYPES as $deliveryType) {
                    $results[$product][$moment][$deliveryType] = $this->aggregatEstimate($product, $productTypeSlug, $quantity, $deliveryType);
                }
            }

            foreach (self::DELIVERY_TYPES as $deliveryType) {
                $response = $this->createOrder($this->courseItem($deliveryType));
                $results['création course'][$moment][$deliveryType] = [
                    'status' => $response->status(),
                    'message' => $response->json('message'),
                ];
            }
        }

        $json = json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

        if (!file_exists(self::SNAPSHOT)) {
            file_put_contents(self::SNAPSHOT, $json);
            $this->markTestIncomplete('Snapshot estimates créé.');
        }

        $this->assertSame(file_get_contents(self::SNAPSHOT), $json);
    }

    private function rideEstimate(int $stops): array
    {
        $routePoints = [$this->destination(['type' => 'source'])];
        foreach (range(1, $stops) as $i) {
            if ($stops > 0) {
                $routePoints[] = $this->destination(['type' => 'arret', 'latitude' => 5.36 + $i / 100]);
            }
        }
        $routePoints[] = $this->destination(['type' => 'destination', 'latitude' => 5.40]);

        $response = $this->actingAs($this->customer, 'api-customers')
            ->postJson('/api/v1/orders/ride/estimate_price', [
                'service_slug' => Service::COURSE,
                'meta_data' => [
                    'type_engin_slug' => 'camion-benne',
                    'engin_model' => '6-roues-8m3',
                    'delivery_type_code' => 'EXPRESS',
                ],
                'route_points' => $routePoints,
            ]);

        return $this->normalize($response->status(), $response->json());
    }

    private function aggregatEstimate(string $product, string $productTypeSlug, int $quantity, string $deliveryType): array
    {
        $response = $this->estimate($product, [
            'service_slug' => Service::AGREGATS_CONSTRUCTION,
            'meta_data' => [
                'product_type_slug' => $productTypeSlug,
                'product_slug' => $product,
                'delivery_type_code' => $deliveryType,
            ],
            'quantity' => $quantity,
            'route_points' => [$this->destination()],
        ]);

        return $this->normalize($response->status(), $response->json());
    }

    private function courseItem(string $deliveryType): array
    {
        return [
            'service_slug' => Service::COURSE,
            'meta_data' => [
                'type_engin_slug' => 'camion-benne',
                'engin_model' => '6-roues-8m3',
                'delivery_type_code' => $deliveryType,
                'delivery_fees' => 20000,
            ],
            'route_points' => [
                $this->destination(['type' => 'source']),
                $this->destination(['type' => 'destination']),
            ],
        ];
    }

    private function normalize(int $status, ?array $body): array
    {
        $data = $body['data'] ?? null;

        if (is_array($data)) {
            // La carrière et les types de livraison sont des lignes complètes : on garde ce qui identifie
            if (isset($data['carrier'])) {
                $data['carrier'] = $data['carrier']['name'];
            }
            foreach ($data['pricings'] ?? [] as $i => $pricing) {
                $data['pricings'][$i]['delivery_type'] = $pricing['delivery_type']['slug'] ?? null;
            }
        }

        return ['status' => $status, 'message' => $body['message'] ?? null, 'data' => $data];
    }
}
