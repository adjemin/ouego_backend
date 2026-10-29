<?php

namespace Tests\Unit;

use App\Models\Carrier;
use App\Models\Driver;
use App\Models\RoutePoint;
use App\Services\AggregatDriverScoringService;
use PHPUnit\Framework\TestCase;

class AggregatDriverScoringServiceTest extends TestCase
{
    private Carrier $carrier;

    private RoutePoint $delivery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrier = (new Carrier())->forceFill(['id' => 7]);
        $this->delivery = (new RoutePoint())->forceFill(['latitude' => 5.36, 'longitude' => -3.99]);
    }

    /** @test */
    public function a_driver_standing_on_the_carrier_does_not_break_the_ranking()
    {
        $ranking = $this->rank([
            $this->driver(1, distance: 0),
            $this->driver(2, distance: 2000),
        ]);

        $this->assertSame([1, 2], array_column($ranking, 'driver_id'));
        $this->assertEquals(100, $ranking[0]['details']['proximity_driver_carrier']);
        $this->assertEquals(5, $ranking[1]['details']['proximity_driver_carrier']);
    }

    /** @test */
    public function distances_below_gps_precision_are_considered_equal()
    {
        $ranking = $this->rank([
            $this->driver(1, distance: 5),
            $this->driver(2, distance: 80),
        ]);

        $this->assertEquals(100, $ranking[0]['details']['proximity_driver_carrier']);
        $this->assertEquals(100, $ranking[1]['details']['proximity_driver_carrier']);
    }

    /** @test */
    public function the_driver_closest_to_the_delivery_point_wins_all_else_equal()
    {
        $ranking = $this->rank([
            $this->driver(1, latitude: 5.30, longitude: -3.99), // ~6,7 km du client
            $this->driver(2, latitude: 5.35, longitude: -3.99), // ~1,1 km du client
        ]);

        $this->assertSame([2, 1], array_column($ranking, 'driver_id'));
        $this->assertEquals(100, $ranking[0]['details']['proximity_driver_delivery']);
        $this->assertLessThan(20, $ranking[1]['details']['proximity_driver_delivery']);
    }

    /** @test */
    public function the_score_applies_the_weights()
    {
        $best = $this->driver(1, distance: 100, balance: 1000, rate: 5, latitude: 5.36, longitude: -3.99);
        $worst = $this->driver(2, distance: 400, balance: 0, rate: 0, latitude: 5.36, longitude: -3.99);

        [$first, $second] = $this->rank([$best, $worst]);

        $this->assertEquals(100, $first['score_total']);
        // 25 % de proximité carrière × 35 % + 0 jeton + 100 % proximité client × 25 % + 0 note
        $this->assertEquals(25 * 0.35 + 100 * 0.25, $second['score_total']);
    }

    /** @test */
    public function negative_balances_count_as_zero_jetons()
    {
        $ranking = $this->rank([
            $this->driver(1, balance: -500),
            $this->driver(2, balance: 0),
        ]);

        $this->assertEquals(0, $ranking[0]['details']['jetons']);
        $this->assertEquals(0, $ranking[1]['details']['jetons']);
    }

    /** @test */
    public function the_delivery_criterion_is_neutral_without_a_delivery_point()
    {
        $ranking = (new AggregatDriverScoringService())->rank(collect([$this->driver(1)]), $this->carrier, null, 5);

        $this->assertEquals(100, $ranking[0]['details']['proximity_driver_delivery']);
    }

    /** @test */
    public function it_keeps_only_the_best_drivers_up_to_the_limit()
    {
        $drivers = [];
        foreach (range(1, 7) as $id) {
            $drivers[] = $this->driver($id, balance: $id * 100);
        }

        $ranking = $this->rank($drivers, 5);

        $this->assertSame([7, 6, 5, 4, 3], array_column($ranking, 'driver_id'));
        $this->assertSame(7, $ranking[0]['carrier_id']);
    }

    /** @test */
    public function it_returns_nothing_without_candidates()
    {
        $this->assertSame([], $this->rank([]));
    }

    private function rank(array $drivers, int $limit = 5): array
    {
        return (new AggregatDriverScoringService())->rank(collect($drivers), $this->carrier, $this->delivery, $limit);
    }

    private function driver(
        int $id,
        float $distance = 1000,
        float $balance = 0,
        float $rate = 0,
        float $latitude = 5.35,
        float $longitude = -3.99
    ): Driver {
        return (new Driver())->forceFill([
            'id' => $id,
            'distance' => $distance,
            'current_balance' => $balance,
            'rate' => $rate,
            'last_location_latitude' => $latitude,
            'last_location_longitude' => $longitude,
        ]);
    }
}
