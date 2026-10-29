<?php

namespace Tests\Unit;

use App\Models\DeliveryType;
use App\Models\Setting;
use Tests\TestCase;

class DeliveryTypeEnJourneeCutoffTest extends TestCase
{
    /** @test */
    public function it_reads_the_cutoff_hour_from_settings()
    {
        Setting::create(['name' => 'JOURNEE_CUTOFF_HOUR', 'value' => '14']);

        $this->assertSame(14, DeliveryType::enJourneeCutoffHour());
    }

    /** @test */
    public function it_falls_back_to_12h_when_the_setting_is_missing()
    {
        $this->assertSame(12, DeliveryType::enJourneeCutoffHour());
    }

    /** @test */
    public function it_falls_back_to_12h_when_the_setting_is_not_a_number()
    {
        Setting::create(['name' => 'JOURNEE_CUTOFF_HOUR', 'value' => '']);

        $this->assertSame(12, DeliveryType::enJourneeCutoffHour());
    }
}
