<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZoneMappingSeeder extends Seeder
{
    public function run(): void
    {
        // Mappings définis directement par [nom_zone, nom_carrière]
        $mappings = [
            // Abobo
            ['Abobo',       'ABEILLE CARRIERE MBRAGO'],
            ['Abobo',       'CMR GRANIT (Carrière)'],
            // Adjamé
            ['Adjamé',      'ABEILLE CARRIERE MBRAGO'],
            ['Adjamé',      'CMR GRANIT (Carrière)'],
            // Attécoubé
            ['Attécoubé',   'ABEILLE CARRIERE MBRAGO'],
            ['Attécoubé',   'CMR GRANIT (Carrière)'],
            // Bingerville
            ['Bingerville', 'ABEILLE CARRIERE MBRAGO'],
            ['Bingerville', 'CMR GRANIT (Carrière)'],
            // Cocody
            ['Cocody',      'Abeille Groupe Carrière Bago'],
            ['Cocody',      'Carrière de granite AMG'],
            ['Cocody',      'Carrière Diamant noir'],
            ['Cocody',      'Carrière Diakité'],
            ['Cocody',      'Carrière PK 36'],
            ['Cocody',      'Carrière soremi'],
            ['Cocody',      'soligra ci'],
            ['Cocody',      'Test Carrière'],
            ['Cocody',      'Carrière Visitée'],
            // Koumassi
            ['Koumassi',    'ABEILLE CARRIERE MBRAGO'],
            ['Koumassi',    'Carrière Agban CEFAL'],
            ['Koumassi',    'Carrière Agban Confianza'],
            ['Koumassi',    'Carrière Agban Primochim'],
            ['Koumassi',    'Carrière Agban SIDCI'],
            ['Koumassi',    'Carrière Agban SMCI'],
            ['Koumassi',    'Carrière Bingerville Anan'],
            ['Koumassi',    'Carrière démo'],
            ['Koumassi',    'Carrière Diamant noir'],
            ['Koumassi',    'Carrière Diakité'],
            ['Koumassi',    'Carrière soremi'],
            ['Koumassi',    'soligra ci'],
            // Marcory
            ['Marcory',     'Carrière PK 36'],
            // Plateau
            ['Plateau',     'CMR GRANIT (Carrière)'],
            // Port-Bouët
            ['Port-Bouët',  'ABEILLE CARRIERE MBRAGO'],
            ['Port-Bouët',  'CMR GRANIT (Carrière)'],
            // Songon
            ['Songon',      'ABEILLE CARRIERE MBRAGO'],
            ['Songon',      'CMR GRANIT (Carrière)'],
            // Treichville
            ['Treichville', 'ABEILLE CARRIERE MBRAGO'],
            ['Treichville', 'Carrière Agban CEFAL'],
            ['Treichville', 'Carrière Agban Confianza'],
            ['Treichville', 'Carrière Agban Primochim'],
            ['Treichville', 'Carrière Agban SIDCI'],
            ['Treichville', 'Carrière Agban SMCI'],
            ['Treichville', 'Carrière Bingerville Anan'],
            ['Treichville', 'Carrière démo'],
            ['Treichville', 'Carrière Diamant noir'],
            ['Treichville', 'Carrière Diakité'],
            ['Treichville', 'Carrière soremi'],
            ['Treichville', 'CMR GRANIT (Carrière)'],
            ['Treichville', 'soligra ci'],
            ['Treichville', 'Test Carrière'],
            // Yopougon
            ['Yopougon',    'ABEILLE CARRIERE MBRAGO'],
            ['Yopougon',    'CMR GRANIT (Carrière)'],
        ];

        // Récupérer les IDs depuis la BD par nom
        $zones    = DB::table('zones')->pluck('id', 'name');
        $carriers = DB::table('carriers')->pluck('id', 'name');

        foreach ($mappings as [$zoneName, $carrierName]) {
            $zoneId    = $zones[$zoneName] ?? null;
            $carrierId = $carriers[$carrierName] ?? null;

            if (!$zoneId || !$carrierId) {
                $this->command->warn("Introuvable : zone=\"{$zoneName}\" carrière=\"{$carrierName}\"");
                continue;
            }

            $exists = DB::table('zone_mapping')
                ->where('zone_id', $zoneId)
                ->where('carrier_id', $carrierId)
                ->exists();

            if (!$exists) {
                DB::table('zone_mapping')->insert([
                    'zone_id'    => $zoneId,
                    'carrier_id' => $carrierId,
                ]);
            }
        }
    }
}
