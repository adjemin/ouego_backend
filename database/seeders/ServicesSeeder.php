<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServicesSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'name' => 'Location',
                'image' => null,
                'description' => "Location d'engins",
                'is_active' => false,
                'slug' => 'location',
                'deleted_at' => null,
                'created_at' => '2026-02-25 23:12:51',
                'updated_at' => '2026-03-07 16:22:04',
            ],
            [
                'name' => 'Course',
                'image' => null,
                'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus eu erat sit amet metus lacinia scelerisque eu eu purus. Nunc sagittis sem venenatis nulla tempor, ultricies placerat nunc bibendum.',
                'is_active' => true,
                'slug' => 'course',
                'deleted_at' => null,
                'created_at' => '2025-08-05 15:10:46',
                'updated_at' => '2026-03-07 18:04:45',
            ],
            [
                'name' => 'Agrégats construction',
                'image' => null,
                'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus eu erat sit amet metus lacinia scelerisque eu eu purus. Nunc sagittis sem venenatis nulla tempor, ultricies placerat nunc bibendum.',
                'is_active' => true,
                'slug' => 'agregats-construction',
                'deleted_at' => null,
                'created_at' => '2025-08-05 15:11:34',
                'updated_at' => '2026-02-23 22:45:39',
            ],
        ];

        foreach ($services as $service) {
            DB::table('services')->updateOrInsert(
                ['slug' => $service['slug']],
                $service
            );
        }
    }
}
