<?php

namespace Tests\Unit;

use App\Models\DeliveryType;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class DeliveryTypeExpressAvailabilityTest extends TestCase
{
    /**
     * @test
     * @dataProvider times
     */
    public function express_is_unavailable_from_06h_to_09h_and_from_17h_to_19h30(string $time, bool $expected)
    {
        $this->assertSame($expected, DeliveryType::isExpressAvailable(Carbon::parse("2026-10-02 {$time}")));
    }

    public static function times(): array
    {
        return [
            '05h59'    => ['05:59:59', true],
            '06h00'    => ['06:00:00', false],
            '08h59'    => ['08:59:59', false],
            '09h00'    => ['09:00:00', true],
            '16h59'    => ['16:59:59', true],
            '17h00'    => ['17:00:00', false],
            '19h29'    => ['19:29:59', false],
            '19h30'    => ['19:30:00', true],
            '23h00'    => ['23:00:00', true],
        ];
    }
}
