<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TypeEnginsSeeder extends Seeder
{
    public function run(): void
    {
        $engins = [
            [
                'name' => 'Camion benne',
                'slug' => 'camion-benne',
                'ability_tonne' => '[3,30]',
                'usage' => 'Sable, gravier, gravats',
                'services' => '["course","agregats-construction","location"]',
                'created_at' => '2025-08-05 15:21:13',
                'updated_at' => '2025-08-05 16:18:51',
                'deleted_at' => null,
            ],
            [
                'name' => 'Camion plateau standard',
                'slug' => 'camion-plateau-standard',
                'ability_tonne' => '[7,26]',
                'usage' => "Sacs de ciment, briques, barres d'acier, tuyaux, planches de bois.",
                'services' => '["course","agregats-construction","location"]',
                'created_at' => '2025-08-05 15:55:41',
                'updated_at' => '2025-08-05 15:55:41',
                'deleted_at' => null,
            ],
            [
                'name' => 'Camion plateau à ridelles',
                'slug' => 'camion-plateau-a-ridelles',
                'ability_tonne' => '[7,26]',
                'usage' => "Sacs de ciment, briques, barres d'acier, tuyaux, planches de bois.",
                'services' => '["course","agregats-construction","location"]',
                'created_at' => '2025-08-05 15:56:51',
                'updated_at' => '2025-08-05 15:56:51',
                'deleted_at' => null,
            ],
            [
                'name' => 'Porteur',
                'slug' => 'porteur',
                'ability_tonne' => '[7,30]',
                'usage' => ' Équipements ou de matériaux divers',
                'services' => '["course","agregats-construction","location"]',
                'created_at' => '2025-08-05 15:58:05',
                'updated_at' => '2025-08-05 15:58:05',
                'deleted_at' => null,
            ],
            [
                'name' => 'Bâché',
                'slug' => 'bache',
                'ability_tonne' => '[2,5]',
                'usage' => 'Divers matériaux',
                'services' => '["course"]',
                'created_at' => '2025-08-13 11:08:39',
                'updated_at' => '2025-08-13 11:08:39',
                'deleted_at' => null,
            ],
        ];

        foreach ($engins as $engin) {
            DB::table('type_engins')->updateOrInsert(
                ['slug' => $engin['slug']],
                $engin
            );
        }
    }
}
