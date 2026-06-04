<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerProfilesSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            [
                'name'        => 'Entrepreneur',
                'description' => 'Vous menez des projets immobiliers pour vos clients.',
                'created_at'  => '2026-02-05 19:13:57',
                'updated_at'  => '2026-02-26 15:55:15',
            ],
            [
                'name'        => 'Constructeur',
                'description' => 'Vous réalisez des travaux de construction.',
                'created_at'  => '2026-02-05 19:43:58',
                'updated_at'  => '2026-02-26 15:55:33',
            ],
            [
                'name'        => 'Chef chantier',
                'description' => 'Vous coordonnez les travaux et l\'approvisionnement du chantier.',
                'created_at'  => '2026-02-18 08:48:27',
                'updated_at'  => '2026-02-26 15:55:46',
            ],
            [
                'name'        => 'Particulier',
                'description' => 'Vous commandez des matériaux ou un camion pour vos besoins personnels.',
                'created_at'  => '2026-02-18 08:49:25',
                'updated_at'  => '2026-02-26 15:56:04',
            ],
        ];

        foreach ($profiles as $profile) {
            DB::table('customer_profiles')->updateOrInsert(
                ['name' => $profile['name']],
                $profile
            );
        }
    }
}
