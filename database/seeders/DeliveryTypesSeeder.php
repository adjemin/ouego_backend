<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeliveryTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Express',
                'icon' => 'https://test-ouego-dashboard.adjemin.com/documents/01KJDQGJKGXTRNX8HV46HRJ194.png',
                'slug' => 'EXPRESS',
                'is_active' => true,
                'pricing_operator' => 'multiply',
                'pricing_value' => 1.0,
                'created_at' => '2025-08-05 17:04:01',
                'updated_at' => '2026-02-26 19:41:17',
                'deleted_at' => null,
            ],
            [
                'name' => 'En journée',
                'icon' => 'https://test-ouego-dashboard.adjemin.com/documents/01KJDQ95E4X29PZ9R1CDWCHD36.png',
                'slug' => 'en-journee',
                'is_active' => true,
                'pricing_operator' => 'divide',
                'pricing_value' => 2.0,
                'created_at' => '2025-08-05 17:04:31',
                'updated_at' => '2026-02-26 19:56:43',
                'deleted_at' => null,
            ],
            [
                'name' => 'De nuit',
                'icon' => 'https://test-ouego-dashboard.adjemin.com/documents/01KJDPT93V557J28S8CG8R30SH.png',
                'slug' => 'de-nuit',
                'is_active' => true,
                'pricing_operator' => 'add_percent',
                'pricing_value' => 150.0,
                'created_at' => '2025-08-05 17:04:53',
                'updated_at' => '2026-02-26 19:57:20',
                'deleted_at' => null,
            ],
            [
                'name' => 'En semaine',
                'icon' => 'https://test-ouego-dashboard.adjemin.com/documents/01KJDR68FSPW6Y7DRK0H5SDF69.png',
                'slug' => 'en-semaine',
                'is_active' => true,
                'pricing_operator' => 'divide',
                'pricing_value' => 3.0,
                'created_at' => '2025-08-05 17:05:13',
                'updated_at' => '2026-02-26 19:59:38',
                'deleted_at' => null,
            ],
        ];

        foreach ($types as $type) {
            DB::table('delivery_types')->updateOrInsert(
                ['slug' => $type['slug']],
                $type
            );
        }
    }
}
