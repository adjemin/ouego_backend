<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeliveryObjectsSeeder extends Seeder
{
    public function run(): void
    {
        $objects = [
            ['name' => 'Sable', 'created_at' => '2025-11-04 22:30:05', 'updated_at' => '2025-11-04 22:30:05'],
            ['name' => 'Gravier', 'created_at' => '2025-11-04 22:30:18', 'updated_at' => '2025-11-04 22:30:18'],
            ['name' => 'Fer', 'created_at' => '2025-11-04 22:30:42', 'updated_at' => '2025-11-04 22:30:42'],
            ['name' => 'Terre', 'created_at' => '2025-12-12 09:23:54', 'updated_at' => '2025-12-12 09:23:54'],
            ['name' => 'Sacs de ciment', 'created_at' => '2025-12-12 09:24:51', 'updated_at' => '2025-12-12 09:24:51'],
            ['name' => 'Carreaux', 'created_at' => '2025-12-12 09:28:44', 'updated_at' => '2025-12-12 09:28:44'],
            ['name' => 'Sanitaires (Lavabo, WC, ...)', 'created_at' => '2025-12-12 09:31:38', 'updated_at' => '2025-12-12 09:31:38'],
        ];

        foreach ($objects as $object) {
            DB::table('delivery_objects')->updateOrInsert(
                ['name' => $object['name']],
                $object
            );
        }
    }
}
