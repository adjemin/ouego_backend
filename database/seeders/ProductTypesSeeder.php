<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductTypesSeeder extends Seeder
{
    public function run(): void
    {
        $gravierProductId = DB::table('products')->where('slug', 'gravier')->value('id');
        $sableProductId = DB::table('products')->where('slug', 'sable')->value('id');

        $types = [
            [
                'product_id' => $gravierProductId,
                'name' => 'Gravier 5/15 (Petit grain)',
                'slug' => 'gravier-515-petit-grain',
                'description' => null,
                'price' => 6500.0,
                'currency_code' => 'XOF',
                'created_at' => '2025-08-05 14:55:23',
                'updated_at' => '2025-09-05 14:40:43',
                'deleted_at' => null,
            ],
            [
                'product_id' => $gravierProductId,
                'name' => 'Gravier 5/25 (Mélange)',
                'slug' => 'gravier-525-melange',
                'description' => null,
                'price' => 6500.0,
                'currency_code' => 'XOF',
                'created_at' => '2025-08-05 14:55:53',
                'updated_at' => '2025-09-05 14:40:53',
                'deleted_at' => null,
            ],
            [
                'product_id' => $gravierProductId,
                'name' => 'Gravier 15/25 (Gros grain)',
                'slug' => 'gravier-1525-gros-grain',
                'description' => null,
                'price' => 6500.0,
                'currency_code' => 'XOF',
                'created_at' => '2025-08-05 14:56:31',
                'updated_at' => '2026-03-05 11:22:12',
                'deleted_at' => null,
            ],
            [
                'product_id' => $sableProductId,
                'name' => 'Sable fin',
                'slug' => 'sable-fin',
                'description' => null,
                'price' => 3000.0,
                'currency_code' => 'XOF',
                'created_at' => '2025-08-05 14:59:04',
                'updated_at' => '2025-08-08 12:51:01',
                'deleted_at' => null,
            ],
            [
                'product_id' => $sableProductId,
                'name' => 'Sable petit grain',
                'slug' => 'sable-petit-grain',
                'description' => null,
                'price' => 4000.0,
                'currency_code' => 'XOF',
                'created_at' => '2025-08-05 14:59:34',
                'updated_at' => '2025-08-08 12:50:34',
                'deleted_at' => null,
            ],
            [
                'product_id' => $sableProductId,
                'name' => 'Sable gros grain',
                'slug' => 'sable-gros-grain',
                'description' => null,
                'price' => 5000.0,
                'currency_code' => 'XOF',
                'created_at' => '2025-08-05 15:00:04',
                'updated_at' => '2025-08-08 12:49:52',
                'deleted_at' => null,
            ],
        ];

        foreach ($types as $type) {
            DB::table('product_types')->updateOrInsert(
                ['slug' => $type['slug']],
                $type
            );
        }
    }
}
