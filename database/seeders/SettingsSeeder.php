<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    /**
     * Seed the settings table.
     */
    public function run(): void
    {
        $settings = [
            // Gravier
            ['name' => 'GRAVIER_DISTANCE_DE_BASE', 'value' => '0'],
            ['name' => 'GRAVIER_QUANTITE_DE_BASE', 'value' => '0'],
            ['name' => 'GRAVIER_PRIX_DE_BASE', 'value' => '0'],
            ['name' => 'GRAVIER_PRIX_KILOMETRE', 'value' => '0'],
            ['name' => 'GRAVIER_PRIX_TONNAGE', 'value' => '0'],
            ['name' => 'GRAVIER_FRAIS_DE_ROUTE', 'value' => '0'],
            ['name' => 'GRAVIER_COMMISSION_OUEGO', 'value' => '0'],
            ['name' => 'GRAVIER_COMMISSION_OUEGO_MIN', 'value' => '0'],

            // Sable
            ['name' => 'SABLE_DISTANCE_DE_BASE', 'value' => '0'],
            ['name' => 'SABLE_PRIX_DE_BASE', 'value' => '0'],
            ['name' => 'SABLE_PRIX_KILOMETRE', 'value' => '0'],
            ['name' => 'SABLE_FRAIS_DE_ROUTE', 'value' => '0'],
            ['name' => 'SABLE_COMMISSION_OUEGO', 'value' => '0'],
            ['name' => 'SABLE_COMMISSION_OUEGO_MIN', 'value' => '0'],

            // Course
            ['name' => 'COURSE_COMMISSION_OUEGO', 'value' => '0'],
            ['name' => 'OUEGO_COMMISSION_COURSE_MIN', 'value' => '0'],
            ['name' => 'FRAIS_ROUTE', 'value' => '0'],

            // Transport / Location
            ['name' => 'TRANSPORT_COMMISSION_OUEGO', 'value' => '0'],
            ['name' => 'LOCATION_COMMISSION_OUEGO', 'value' => '0'],
            ['name' => 'LOCATION_COMMISSION_OUEGO_MIN', 'value' => '0'],

            // Tarification
            ['name' => 'PRIX_CARBURANT', 'value' => '0'],
            ['name' => 'CONSO_LITRE', 'value' => '0'],
            ['name' => 'MARGE_CHAUFFEUR_COURSE', 'value' => '0'],
            ['name' => 'TAXE', 'value' => '0'],

            // Journée
            ['name' => 'JOURNEE_CUTOFF_HOUR', 'value' => '12'],

            // Commercial
            ['name' => 'COMMERCIAL_DISCOUNT_AMOUNT', 'value' => '2500'],
            ['name' => 'COMMERCIAL_CREDIT_AMOUNT', 'value' => '2500'],
            ['name' => 'COMMERCIAL_MAX_ORDERS', 'value' => '5'],
            ['name' => 'COMMERCIAL_END_DATE', 'value' => '2026-12-31'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['name' => $setting['name']],
                ['value' => $setting['value']]
            );
        }
    }
}
