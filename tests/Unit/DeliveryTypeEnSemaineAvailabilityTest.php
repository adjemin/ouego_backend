<?php

namespace Tests\Unit;

use App\Models\DeliveryType;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class DeliveryTypeEnSemaineAvailabilityTest extends TestCase
{
    /**
     * @test
     * @dataProvider days
     */
    public function en_semaine_can_only_be_ordered_from_monday_to_thursday(string $date, bool $expected)
    {
        $this->assertSame($expected, DeliveryType::isDeSemaineAvailable(Carbon::parse($date)));
    }

    public static function days(): array
    {
        return [
            'lundi 00h00'    => ['2026-09-21 00:00:00', true],
            'mardi'          => ['2026-09-22 10:00:00', true],
            'mercredi'       => ['2026-09-23 10:00:00', true],
            'jeudi 23h59'    => ['2026-09-24 23:59:59', true],
            'vendredi 00h00' => ['2026-09-25 00:00:00', false],
            'samedi'         => ['2026-09-26 10:00:00', false],
            'dimanche'       => ['2026-09-27 10:00:00', false],
        ];
    }
}
