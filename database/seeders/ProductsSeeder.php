<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductsSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Gravier',
                'slug' => 'gravier',
                'per' => 'T',
                'pricing_title' => null,
                'description' => "Les classifications de gravier comme 5/15, 5/25, et 15/25 font référence aux dimensions des granulats en millimètres, indiquant la plage de taille des particules de gravier. Ces mesures sont cruciales pour déterminer l'application appropriée du gravier da",
                'color' => '#ebdd5f',
                'icon' => 'https://ouego-dashboard.adjemin.com/documents/01K1XBKB43PJ8SVN6XCSWJ8GZ3.png',
                'tonne_options' => '[20,25,30,35,40]',
                'pricings' => '[]',
                'created_at' => '2025-08-05 14:54:05',
                'updated_at' => '2025-08-06 12:00:36',
                'deleted_at' => null,
            ],
            [
                'name' => 'Sable',
                'slug' => 'sable',
                'per' => 'T',
                'pricing_title' => null,
                'description' => 'Le sable est un composant essentiel dans le secteur de la construction, utilisé pour diverses applications telles que le mortier, le béton, et le remblayage.',
                'color' => '#ed5d3e',
                'icon' => 'https://ouego-dashboard.adjemin.com/documents/01K1XBTWDH3QXND4H8EPH741EX.png',
                'tonne_options' => '[]',
                'pricings' => '[{"name":"6 roues (8m3)","roues":"6","price":"25000"},{"name":"10 roues (12m3)","roues":"10","price":"45000"},{"name":"12 roues (20m3)","roues":"12","price":"90000"}]',
                'created_at' => '2025-08-05 14:58:12',
                'updated_at' => '2025-08-08 15:13:28',
                'deleted_at' => null,
            ],
        ];

        foreach ($products as $product) {
            DB::table('products')->updateOrInsert(
                ['slug' => $product['slug']],
                $product
            );
        }
    }
}
