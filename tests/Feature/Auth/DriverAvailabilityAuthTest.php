<?php

namespace Tests\Feature\Auth;

use App\Models\Driver;
use Tests\TestCase;

/**
 * La disponibilité et la position d'un chauffeur pilotent l'attribution des
 * courses : seul le chauffeur connecté peut modifier les siennes.
 */
class DriverAvailabilityAuthTest extends TestCase
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
    public function an_anonymous_request_is_rejected()
    {
        $this->putJson($this->url($this->driver), $this->payload())
            ->assertUnauthorized();

        $this->assertDriverUnchanged();
    }

    /** @test */
    public function a_driver_cannot_update_another_driver()
    {
        $token = auth('api-drivers')->login(Driver::factory()->create());

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->putJson($this->url($this->driver), $this->payload())
            ->assertForbidden();

        $this->assertDriverUnchanged();
    }

    /** @test */
    public function a_driver_updates_their_own_availability()
    {
        $token = auth('api-drivers')->login($this->driver);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->putJson($this->url($this->driver), $this->payload())
            ->assertOk()
            ->assertJsonPath('data.user.id', $this->driver->id);

        $this->driver->refresh();
        $this->assertTrue((bool) $this->driver->is_available);
        $this->assertEquals(5.35, $this->driver->last_location_latitude);
        $this->assertEquals(-4.01, $this->driver->last_location_longitude);
    }

    private function url(Driver $driver): string
    {
        return "api/v1/drivers/availabilities/{$driver->id}/update";
    }

    private function payload(): array
    {
        return ['is_available' => true, 'latitude' => 5.35, 'longitude' => -4.01];
    }

    private function assertDriverUnchanged(): void
    {
        $this->driver->refresh();
        $this->assertFalse((bool) $this->driver->is_available);
        $this->assertEquals(5.30, $this->driver->last_location_latitude);
        $this->assertEquals(-4.00, $this->driver->last_location_longitude);
    }
}
