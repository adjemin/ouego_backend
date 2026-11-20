<?php

namespace Tests\Feature\Drivers;

use App\Models\Driver;
use App\Models\Engin;
use App\Models\TypeEnginModel;
use Tests\TestCase;

class DriverCarTest extends TestCase
{
    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = Driver::factory()->create();
        TypeEnginModel::create(['type_engin_slug' => 'camion-benne', 'title' => '6 roues (8m3)', 'slug' => '6-roues-8m3']);
        TypeEnginModel::create(['type_engin_slug' => 'camion-plateau-standard', 'title' => '4 roues', 'slug' => '4-roues']);
    }

    /** @test */
    public function a_car_can_be_created_with_its_model_and_gets_the_model_type()
    {
        $this->actingAs($this->driver, 'api-drivers')
            ->postJson('/api/v1/drivers/cars/create', ['type_engin_model_slug' => '6-roues-8m3'])
            ->assertOk();

        $car = Engin::where('driver_id', (string) $this->driver->id)->sole();
        $this->assertSame('6-roues-8m3', $car->type_engin_model_slug);
        $this->assertSame('camion-benne', $car->type_engin);
    }

    /** @test */
    public function a_car_can_still_be_created_without_model()
    {
        $this->actingAs($this->driver, 'api-drivers')
            ->postJson('/api/v1/drivers/cars/create', ['car_docs' => ['carte-grise.pdf']])
            ->assertOk();

        $car = Engin::where('driver_id', (string) $this->driver->id)->sole();
        $this->assertNull($car->type_engin_model_slug);
        $this->assertNull($car->type_engin);
    }

    /** @test */
    public function an_unknown_model_is_rejected_at_creation()
    {
        $this->actingAs($this->driver, 'api-drivers')
            ->postJson('/api/v1/drivers/cars/create', ['type_engin_model_slug' => 'inconnu'])
            ->assertStatus(400);

        $this->assertSame(0, Engin::count());
    }

    /** @test */
    public function the_driver_can_set_the_model_of_his_car()
    {
        $car = Engin::create(['driver_id' => $this->driver->id, 'type_engin' => 'camion-benne']);

        $this->actingAs($this->driver, 'api-drivers')
            ->putJson("/api/v1/drivers/cars/{$car->id}/update", ['type_engin_model_slug' => '4-roues', 'color' => 'rouge'])
            ->assertOk();

        $car->refresh();
        $this->assertSame('4-roues', $car->type_engin_model_slug);
        $this->assertSame('camion-plateau-standard', $car->type_engin);
        $this->assertSame('rouge', $car->color);
    }

    /** @test */
    public function an_unknown_model_is_rejected_at_update()
    {
        $car = Engin::create(['driver_id' => $this->driver->id, 'type_engin_model_slug' => '6-roues-8m3', 'type_engin' => 'camion-benne']);

        $this->actingAs($this->driver, 'api-drivers')
            ->putJson("/api/v1/drivers/cars/{$car->id}/update", ['type_engin_model_slug' => 'inconnu'])
            ->assertStatus(400);

        $this->assertSame('6-roues-8m3', $car->fresh()->type_engin_model_slug);
    }

    /** @test */
    public function a_driver_cannot_update_the_car_of_another_driver()
    {
        $other = Driver::factory()->create();
        $car = Engin::create(['driver_id' => $other->id, 'color' => 'bleu']);

        $this->actingAs($this->driver, 'api-drivers')
            ->putJson("/api/v1/drivers/cars/{$car->id}/update", ['color' => 'rouge'])
            ->assertNotFound();

        $this->assertSame('bleu', $car->fresh()->color);
    }

    /** @test */
    public function the_owner_of_a_car_cannot_be_changed()
    {
        $other = Driver::factory()->create();
        $car = Engin::create(['driver_id' => $this->driver->id]);

        $this->actingAs($this->driver, 'api-drivers')
            ->putJson("/api/v1/drivers/cars/{$car->id}/update", ['driver_id' => $other->id])
            ->assertOk();

        $this->assertSame((string) $this->driver->id, (string) $car->fresh()->driver_id);
    }
}
