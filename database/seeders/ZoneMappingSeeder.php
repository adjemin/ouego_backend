<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZoneMappingSeeder extends Seeder
{
    public function run(): void
    {
        // Build zone name -> id and carrier name -> id maps
        $zones = DB::table('zones')->pluck('id', 'name');
        $carriers = DB::table('carriers')->pluck('id', 'name');

        // Mapping from dump (zone_id order matches zone insertion order)
        // zone_id 1=Cocody, 2=Koumassi, 3=Treichville, 4=Adjamé, 5=Yopougon, 6=Plateau,
        // 7=Attécoubé, 8=Marcory, 9=Bingerville, 10=Port-Bouët, 11=Abobo, 12=Songon
        $zoneIdToName = [
            1 => 'Cocody',
            2 => 'Koumassi',
            3 => 'Treichville',
            4 => 'Adjamé',
            5 => 'Yopougon',
            6 => 'Plateau',
            7 => 'Attécoubé',
            8 => 'Marcory',
            9 => 'Bingerville',
            10 => 'Port-Bouët',
            14 => 'Abobo',
            19 => 'Songon',
        ];

        // carrier_id order matches carriers insertion order
        $carrierIdToName = [
            2  => 'Carrière PK 36',
            3  => 'Carrière de granite AMG',
            4  => 'Carrière Visitée',
            5  => 'Abeille Groupe Carrière Bago',
            6  => 'CMR GRANIT (Carrière)',
            7  => 'ABEILLE CARRIERE MBRAGO',
            8  => 'Carrière soremi',
            10 => 'soligra ci',
            16 => 'Carrière Diamant noir',
            17 => 'Carrière Diakité',
            20 => 'Carrière Agban SIDCI',
            21 => 'Carrière Bingerville Anan',
            22 => 'Carrière Agban SMCI',
            23 => 'Carrière Agban Confianza',
            24 => 'Carrière Agban Primochim',
            27 => 'Carrière Agban CEFAL',
            29 => 'Carrière démo',
            30 => 'Test Carrière',
        ];

        $mappings = [
            [1, 2], [1, 4], [1, 5], [3, 16], [2, 16], [1, 16],
            [2, 17], [1, 17], [3, 17], [2, 10], [3, 10], [1, 10],
            [2, 8], [3, 8], [1, 8], [2, 21], [3, 21], [2, 22],
            [3, 22], [2, 23], [3, 23], [2, 24], [3, 24], [1, 3],
            [4, 6], [10, 6], [5, 6], [6, 6], [7, 6], [4, 7],
            [10, 7], [9, 7], [7, 7], [5, 7], [9, 6], [8, 2],
            [3, 20], [2, 20], [3, 27], [2, 27], [14, 7], [19, 7],
            [14, 6], [19, 6], [3, 6], [2, 7], [3, 7], [2, 29],
            [3, 29], [3, 30], [1, 30],
        ];

        foreach ($mappings as [$dumpZoneId, $dumpCarrierId]) {
            $zoneName = $zoneIdToName[$dumpZoneId] ?? null;
            $carrierName = $carrierIdToName[$dumpCarrierId] ?? null;

            if (!$zoneName || !$carrierName) {
                continue;
            }

            $zoneId = $zones[$zoneName] ?? null;
            $carrierId = $carriers[$carrierName] ?? null;

            if (!$zoneId || !$carrierId) {
                continue;
            }

            $exists = DB::table('zone_mapping')
                ->where('zone_id', $zoneId)
                ->where('carrier_id', $carrierId)
                ->exists();

            if (!$exists) {
                DB::table('zone_mapping')->insert([
                    'zone_id' => $zoneId,
                    'carrier_id' => $carrierId,
                ]);
            }
        }
    }
}
