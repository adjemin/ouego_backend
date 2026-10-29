<?php

namespace Tests\Feature\Orders;

use App\Models\DeliveryType;
use App\Models\Service;
use Database\Seeders\TypeEnginModelsSeeder;
use Database\Seeders\TypeEnginsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Orders\Aggregats\AggregatOrderTestCase;

class EnSemaineAvailabilityTest extends AggregatOrderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        $this->seed([TypeEnginsSeeder::class, TypeEnginModelsSeeder::class]);
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

    /** @test */
    public function an_en_semaine_order_is_refused_on_friday()
    {
        $this->travelTo(Carbon::parse('2026-09-25 10:00:00'));

        $this->createOrder($this->courseItem(DeliveryType::TYPE_DE_SEMAINE))
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', DeliveryType::DE_SEMAINE_UNAVAILABLE_MESSAGE);

        $this->assertDatabaseCount('orders', 0);
    }

    /** @test */
    public function the_rule_does_not_affect_other_delivery_types_on_friday()
    {
        $this->travelTo(Carbon::parse('2026-09-25 10:00:00'));

        $this->createOrder($this->courseItem(DeliveryType::TYPE_EXPRESS))->assertOk();
    }

    /** @test */
    public function an_en_semaine_order_is_accepted_on_thursday()
    {
        $this->travelTo(Carbon::parse('2026-09-24 10:00:00'));

        $this->createOrder($this->courseItem(DeliveryType::TYPE_DE_SEMAINE))->assertOk();
    }

    /** @test */
    public function the_ride_estimate_marks_en_semaine_unavailable_on_saturday()
    {
        $this->travelTo(Carbon::parse('2026-09-26 10:00:00'));

        $pricings = collect($this->actingAs($this->customer, 'api-customers')
            ->postJson('/api/v1/orders/ride/estimate_price', [
                'service_slug' => Service::COURSE,
                'meta_data' => ['type_engin_slug' => 'camion-benne', 'engin_model' => '6-roues-8m3', 'delivery_type_code' => 'EXPRESS'],
                'route_points' => [$this->destination(['type' => 'source']), $this->destination(['type' => 'destination'])],
            ])
            ->assertOk()
            ->json('data.pricings'))->keyBy('delivery_type.slug');

        $this->assertFalse($pricings[DeliveryType::TYPE_DE_SEMAINE]['is_available']);
        $this->assertSame(DeliveryType::DE_SEMAINE_UNAVAILABLE_MESSAGE, $pricings[DeliveryType::TYPE_DE_SEMAINE]['error_message']);
        $this->assertTrue($pricings[DeliveryType::TYPE_EXPRESS]['is_available']);
    }

    /** @test */
    public function the_aggregat_estimate_marks_en_semaine_unavailable_on_sunday()
    {
        $this->travelTo(Carbon::parse('2026-09-27 10:00:00'));

        $this->estimate('gravier', [
            'service_slug' => Service::AGREGATS_CONSTRUCTION,
            'meta_data' => ['product_type_slug' => 'gravier-515-petit-grain', 'product_slug' => 'gravier', 'delivery_type_code' => DeliveryType::TYPE_DE_SEMAINE],
            'quantity' => 25,
            'route_points' => [$this->destination()],
        ])
            ->assertOk()
            ->assertJsonPath('data.is_available', false)
            ->assertJsonPath('data.error_message', DeliveryType::DE_SEMAINE_UNAVAILABLE_MESSAGE);
    }
}
