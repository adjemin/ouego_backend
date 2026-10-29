<?php

namespace Tests\Unit;

use App\Models\DeliveryType;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class DeliveryTypeEnJourneeAvailabilityTest extends TestCase
{
    /**
     * @test
     * @dataProvider times
     */
    public function en_journee_is_available_from_06h_until_the_cutoff_hour(string $time, bool $expected)
    {
        $this->assertSame($expected, DeliveryType::isEnJourneeAvailable(Carbon::parse("2026-10-02 {$time}"), 12));
    }

    public static function times(): array
    {
        return [
            '05h59' => ['05:59:59', false],
            '06h00' => ['06:00:00', true],
            '11h59' => ['11:59:59', true],
            '12h00' => ['12:00:00', false],
            '12h59' => ['12:59:59', false],
            '23h00' => ['23:00:00', false],
        ];
    }

    /** @test */
    public function the_cutoff_hour_moves_the_end_of_the_window()
    {
        $this->assertTrue(DeliveryType::isEnJourneeAvailable(Carbon::parse('2026-10-02 13:30'), 14));
        $this->assertFalse(DeliveryType::isEnJourneeAvailable(Carbon::parse('2026-10-02 14:00'), 14));
    }

    /** @test */
    public function the_message_shows_the_cutoff_hour()
    {
        $this->assertSame(
            'Vous pouvez passer une course en journée uniquement de 06H00 à 12H00.',
            DeliveryType::enJourneeUnavailableMessage(12)
        );
    }
}
