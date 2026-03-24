<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Reference data (no dependencies)
            ServicesSeeder::class,
            DeliveryTypesSeeder::class,
            DeliveryObjectsSeeder::class,
            SettingsSeeder::class,
            UsersSeeder::class,

            // Products (no dependencies)
            ProductsSeeder::class,
            ProductTypesSeeder::class, // depends on products

            // Engins (no dependencies)
            TypeEnginsSeeder::class,
            TypeEnginModelsSeeder::class, // depends on type_engins

            // Carriers & Zones (no dependencies)
            CarriersSeeder::class,
            ZonesSeeder::class,
            ZoneMappingSeeder::class, // depends on zones + carriers
        ]);
    }
}
