<?php

namespace Tests\Unit;

use App\Models\DeliveryType;
use App\Models\Setting;
use Database\Seeders\DeliveryTypesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingsCacheTest extends TestCase
{
    /** @test */
    public function settings_are_loaded_in_a_single_query()
    {
        Setting::create(['name' => 'A', 'value' => '1']);
        Setting::create(['name' => 'B', 'value' => '2']);

        DB::enableQueryLog();
        $values = [Setting::get('A'), Setting::get('B'), Setting::get('MISSING'), Setting::get('A')];
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(['1', '2', null, '1'], $values);
        $this->assertSame(1, $queries);
    }

    /** @test */
    public function updating_a_setting_refreshes_the_cache()
    {
        $setting = Setting::create(['name' => 'JOURNEE_CUTOFF_HOUR', 'value' => '12']);
        $this->assertSame('12', Setting::get('JOURNEE_CUTOFF_HOUR'));

        $setting->update(['value' => '14']);
        $this->assertSame('14', Setting::get('JOURNEE_CUTOFF_HOUR'));

        $setting->delete();
        $this->assertNull(Setting::get('JOURNEE_CUTOFF_HOUR'));
    }

    /** @test */
    public function get_all_returns_the_settings_by_name()
    {
        Setting::create(['name' => 'A', 'value' => '1']);
        Setting::create(['name' => 'B', 'value' => '2']);

        $this->assertSame(['A' => '1', 'B' => '2'], Setting::getAll());
    }

    /** @test */
    public function update_value_creates_or_updates_the_setting()
    {
        $this->assertTrue(Setting::updateValue('JOURNEE_CUTOFF_HOUR', '12'));
        $this->assertSame('12', Setting::get('JOURNEE_CUTOFF_HOUR'));

        $this->assertTrue(Setting::updateValue('JOURNEE_CUTOFF_HOUR', '14'));
        $this->assertSame('14', Setting::get('JOURNEE_CUTOFF_HOUR'));
        $this->assertSame(1, Setting::where('name', 'JOURNEE_CUTOFF_HOUR')->count());
    }

    /** @test */
    public function delivery_types_are_cached_by_slug()
    {
        $this->seed(DeliveryTypesSeeder::class);

        DB::enableQueryLog();
        $express = DeliveryType::findBySlug(DeliveryType::TYPE_EXPRESS);
        $night = DeliveryType::findBySlug(DeliveryType::TYPE_DE_NUIT);
        $missing = DeliveryType::findBySlug('inconnu');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(DeliveryType::TYPE_EXPRESS, $express->slug);
        $this->assertSame(DeliveryType::TYPE_DE_NUIT, $night->slug);
        $this->assertNull($missing);
        $this->assertSame(1, $queries);
    }

    /** @test */
    public function updating_a_delivery_type_refreshes_the_cache()
    {
        $this->seed(DeliveryTypesSeeder::class);
        DeliveryType::findBySlug(DeliveryType::TYPE_EXPRESS);

        DeliveryType::where('slug', DeliveryType::TYPE_EXPRESS)->first()->update(['pricing_value' => 42]);

        $this->assertSame(42.0, DeliveryType::findBySlug(DeliveryType::TYPE_EXPRESS)->pricing_value);
    }
}
