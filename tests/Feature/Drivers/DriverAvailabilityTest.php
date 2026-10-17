<?php

namespace Tests\Feature\Drivers;

use App\Models\Driver;
use Tests\TestCase;

/**
 * La disponibilité et la position d'un chauffeur déterminent les commandes qui lui sont proposées :
 * seul le chauffeur lui-même peut les modifier.
 */
class DriverAvailabilityTest extends TestCase
{
    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = Driver::factory()->create([
            'is_available' => false,
            'last_location_latitude' => 5.30,
            'last_location_longitude' => -4.00,
        ]);
    }

    /** @test */
    public function a_driver_updates_their_own_availability_and_location()
    {
        $this->actingAs($this->driver, 'api-drivers')
            ->putJson("/api/v1/drivers/availabilities/{$this->driver->id}/update", [
                'is_available' => true,
                'latitude' => 5.36,
                'longitude' => -3.99,
            ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $this->driver->id);

        $driver = $this->driver->fresh();
        $this->assertTrue((bool) $driver->is_available);
        $this->assertEquals(5.36, $driver->last_location_latitude);
        $this->assertEquals(-3.99, $driver->last_location_longitude);
    }

    /** @test */
    public function a_guest_cannot_update_a_driver_availability()
    {
        $this->putJson("/api/v1/drivers/availabilities/{$this->driver->id}/update", [
            'is_available' => true,
            'latitude' => 5.36,
            'longitude' => -3.99,
        ])->assertStatus(401);

        $this->assertDriverUnchanged();
    }

    /** @test */
    public function a_driver_cannot_update_another_driver_availability()
    {
        $intruder = Driver::factory()->create();

        $this->actingAs($intruder, 'api-drivers')
            ->putJson("/api/v1/drivers/availabilities/{$this->driver->id}/update", [
                'is_available' => true,
                'latitude' => 5.36,
                'longitude' => -3.99,
            ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Chauffeur introuvable');

        $this->assertDriverUnchanged();
    }

    /**
     * @test
     * @dataProvider missingCoordinates
     */
    public function the_location_is_required(string $missing)
    {
        $payload = array_diff_key(['latitude' => 5.36, 'longitude' => -3.99], [$missing => true]);

        $this->actingAs($this->driver, 'api-drivers')
            ->putJson("/api/v1/drivers/availabilities/{$this->driver->id}/update", $payload)
            ->assertStatus(400);

        $this->assertDriverUnchanged();
    }

    public static function missingCoordinates(): array
    {
        return ['latitude' => ['latitude'], 'longitude' => ['longitude']];
    }

    private function assertDriverUnchanged(): void
    {
        $driver = $this->driver->fresh();
        $this->assertFalse((bool) $driver->is_available);
        $this->assertEquals(5.30, $driver->last_location_latitude);
        $this->assertEquals(-4.00, $driver->last_location_longitude);
    }
}
