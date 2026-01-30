<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => $this->faker->email(),
            'phone' => $this->faker->phoneNumber(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'first_name' => $this->faker->name(),
            'last_name' => $this->faker->name(),
            'name' => $this->faker->name(),
            'dialing_code' => "225",
            'phone_number' => $this->faker->phoneNumber(),
            'phone' => $this->faker->phoneNumber(),
            'photo_url' => $this->faker->imageUrl(),
           'is_active' => true,
            'current_balance' => $this->faker->randomFloat(2, 0, 1000),
            'old_balance' => $this->faker->randomFloat(2, 0, 1000),
            'is_available' => true,
            'last_location_latitude' => $this->faker->latitude(),
            'last_location_longitude' => $this->faker->longitude(),
            'services'  => '["delivery"]',
            'driver_license_docs' => '[]',
            'rate' => $this->faker->randomFloat(2, 0, 5),
        ];
    }
}
