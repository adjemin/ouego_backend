<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CarriersSeeder extends Seeder
{
    public function run(): void
    {
        $carriers = [
            // Carrières Gravier
            ['name' => 'SISAG Carrière', 'phone' => null, 'location_latitude' => 5.4848843, 'location_longitude' => -4.23473, 'is_active' => true, 'products' => '["gravier-515-petit-grain","gravier-525-melange","gravier-1525-gros-grain"]', 'aggregats' => '["1"]', 'photo' => null, 'created_at' => '2025-08-06 11:02:56', 'updated_at' => '2025-08-06 11:02:56', 'deleted_at' => null],
            ['name' => 'Carrière PK 36', 'phone' => null, 'location_latitude' => 5.4885676, 'location_longitude' => -4.2271311, 'is_active' => true, 'products' => '["gravier-515-petit-grain","gravier-525-melange","gravier-1525-gros-grain"]', 'aggregats' => '["1"]', 'photo' => null, 'created_at' => '2025-08-06 11:05:05', 'updated_at' => '2025-08-06 11:05:05', 'deleted_at' => null],
            ['name' => 'Carrière de granite AMG', 'phone' => null, 'location_latitude' => 5.4979696, 'location_longitude' => -4.2151742, 'is_active' => true, 'products' => '["gravier-515-petit-grain","gravier-525-melange","gravier-1525-gros-grain"]', 'aggregats' => '["1"]', 'photo' => null, 'created_at' => '2025-08-06 11:05:58', 'updated_at' => '2025-08-06 11:05:58', 'deleted_at' => null],
            ['name' => 'Carrière Visitée', 'phone' => null, 'location_latitude' => 5.5012956, 'location_longitude' => -4.2372683, 'is_active' => true, 'products' => '["gravier-515-petit-grain","gravier-525-melange","gravier-1525-gros-grain"]', 'aggregats' => '["1"]', 'photo' => null, 'created_at' => '2025-08-06 11:11:53', 'updated_at' => '2025-08-06 11:11:53', 'deleted_at' => null],
            ['name' => 'Abeille Groupe Carrière Bago', 'phone' => null, 'location_latitude' => 5.4873101, 'location_longitude' => -4.2938635, 'is_active' => true, 'products' => '["gravier-515-petit-grain","gravier-525-melange","gravier-1525-gros-grain"]', 'aggregats' => '["1"]', 'photo' => null, 'created_at' => '2025-08-06 11:10:07', 'updated_at' => '2025-08-06 11:12:19', 'deleted_at' => null],
            ['name' => 'CMR GRANIT (Carrière)', 'phone' => null, 'location_latitude' => 5.4948509, 'location_longitude' => -4.2532607, 'is_active' => true, 'products' => '["gravier-515-petit-grain","gravier-525-melange","gravier-1525-gros-grain"]', 'aggregats' => '["1"]', 'photo' => null, 'created_at' => '2025-08-06 11:09:11', 'updated_at' => '2025-08-06 11:12:28', 'deleted_at' => null],
            ['name' => 'ABEILLE CARRIERE MBRAGO', 'phone' => null, 'location_latitude' => 5.5268899, 'location_longitude' => -4.2346807, 'is_active' => true, 'products' => '["gravier-515-petit-grain","gravier-525-melange","gravier-1525-gros-grain"]', 'aggregats' => '["1"]', 'photo' => null, 'created_at' => '2025-08-06 11:08:22', 'updated_at' => '2025-08-06 11:12:39', 'deleted_at' => null],
            ['name' => 'Carrière soremi', 'phone' => null, 'location_latitude' => 5.5139476, 'location_longitude' => -4.2094897, 'is_active' => true, 'products' => '["gravier-515-petit-grain","gravier-525-melange","gravier-1525-gros-grain"]', 'aggregats' => '["1"]', 'photo' => null, 'created_at' => '2025-08-06 11:07:36', 'updated_at' => '2025-08-06 11:12:48', 'deleted_at' => null],
            ['name' => 'soligra ci', 'phone' => null, 'location_latitude' => 5.505189, 'location_longitude' => -4.213053, 'is_active' => true, 'products' => '["gravier-515-petit-grain","gravier-525-melange","gravier-1525-gros-grain"]', 'aggregats' => '["1"]', 'photo' => null, 'created_at' => '2025-08-06 11:06:45', 'updated_at' => '2025-08-06 11:13:00', 'deleted_at' => null],
            ['name' => 'Carrière YAO 姚氏矿业', 'phone' => null, 'location_latitude' => 5.4765462, 'location_longitude' => -4.2396099, 'is_active' => true, 'products' => '[]', 'aggregats' => '["1"]', 'photo' => null, 'created_at' => '2025-08-06 11:04:13', 'updated_at' => '2025-08-06 11:13:10', 'deleted_at' => null],
            ['name' => 'Carriere Kossihouen CADERAC', 'phone' => null, 'location_latitude' => 5.5140625, 'location_longitude' => -4.2800625, 'is_active' => false, 'products' => '["gravier-515-petit-grain","gravier-525-melange","gravier-1525-gros-grain"]', 'aggregats' => '["1"]', 'photo' => null, 'created_at' => '2025-08-06 11:11:14', 'updated_at' => '2025-12-23 01:25:43', 'deleted_at' => null],

            // Carrières Sable
            ['name' => 'Carrière Diamant noir', 'phone' => null, 'location_latitude' => 5.3056049, 'location_longitude' => -4.0430449, 'is_active' => false, 'products' => '["sable-fin","sable-petit-grain","sable-gros-grain"]', 'aggregats' => '["2"]', 'photo' => null, 'created_at' => '2025-08-06 07:29:40', 'updated_at' => '2025-11-28 09:15:19', 'deleted_at' => null],
            ['name' => 'Carrière Diakité', 'phone' => null, 'location_latitude' => 5.3106568, 'location_longitude' => -4.0606172, 'is_active' => false, 'products' => '["sable-petit-grain","sable-fin","sable-gros-grain"]', 'aggregats' => '["2"]', 'photo' => null, 'created_at' => '2025-08-06 07:28:36', 'updated_at' => '2025-11-28 09:15:37', 'deleted_at' => null],
            ['name' => 'Carrière De Sable Tian.cheng', 'phone' => null, 'location_latitude' => 5.3050669, 'location_longitude' => -4.0848469, 'is_active' => false, 'products' => '["sable-fin","sable-petit-grain","sable-gros-grain"]', 'aggregats' => '["2"]', 'photo' => null, 'created_at' => '2025-08-06 07:26:35', 'updated_at' => '2025-11-28 09:15:54', 'deleted_at' => null],
            ['name' => 'Carrière Koumassi Bd Marseille', 'phone' => null, 'location_latitude' => 5.2811203, 'location_longitude' => -3.9844617, 'is_active' => false, 'products' => '["sable-petit-grain","sable-fin","sable-gros-grain"]', 'aggregats' => '["2"]', 'photo' => null, 'created_at' => '2025-08-06 07:32:46', 'updated_at' => '2025-11-28 09:20:35', 'deleted_at' => null],
            ['name' => 'Carrière Abobo Doumé', 'phone' => null, 'location_latitude' => 5.3057777, 'location_longitude' => -4.0397004, 'is_active' => true, 'products' => '["sable-gros-grain"]', 'aggregats' => '["2"]', 'photo' => null, 'created_at' => '2025-08-06 07:30:44', 'updated_at' => '2025-12-23 01:26:41', 'deleted_at' => null],
            ['name' => 'Carrière Koumassi commando', 'phone' => null, 'location_latitude' => 5.2796485, 'location_longitude' => -3.9611065, 'is_active' => true, 'products' => '["sable-fin","sable-gros-grain","sable-petit-grain"]', 'aggregats' => '["2"]', 'photo' => null, 'created_at' => '2025-08-06 07:31:36', 'updated_at' => '2026-02-25 12:32:05', 'deleted_at' => null],
            ['name' => 'Carrière Agban SIDCI', 'phone' => null, 'location_latitude' => 5.3138766, 'location_longitude' => -3.8678384, 'is_active' => false, 'products' => '["sable-gros-grain","sable-petit-grain","sable-fin"]', 'aggregats' => '["2"]', 'photo' => null, 'created_at' => '2025-08-08 19:59:38', 'updated_at' => '2025-11-28 09:14:41', 'deleted_at' => null],
            ['name' => 'Carrière Bingerville Anan', 'phone' => null, 'location_latitude' => 5.3105072, 'location_longitude' => -3.8638471, 'is_active' => false, 'products' => '["sable-gros-grain","sable-petit-grain","sable-fin"]', 'aggregats' => '["2"]', 'photo' => null, 'created_at' => '2025-10-18 23:48:40', 'updated_at' => '2025-10-27 21:44:18', 'deleted_at' => null],
            ['name' => 'Carrière Agban SMCI', 'phone' => null, 'location_latitude' => 5.3026638, 'location_longitude' => -3.8486953, 'is_active' => false, 'products' => '["sable-gros-grain","sable-petit-grain","sable-fin"]', 'aggregats' => '["2"]', 'photo' => null, 'created_at' => '2025-10-18 23:50:20', 'updated_at' => '2025-11-28 09:13:33', 'deleted_at' => null],
            ['name' => 'Carrière Agban Confianza', 'phone' => null, 'location_latitude' => 5.3032241, 'location_longitude' => -3.8450038, 'is_active' => false, 'products' => '["sable-gros-grain","sable-petit-grain","sable-fin"]', 'aggregats' => '["2"]', 'photo' => null, 'created_at' => '2025-10-18 23:49:36', 'updated_at' => '2025-12-09 09:23:02', 'deleted_at' => null],
            ['name' => 'Carrière Agban Primochim', 'phone' => null, 'location_latitude' => 5.3105049, 'location_longitude' => -3.8662853, 'is_active' => false, 'products' => '["sable-gros-grain","sable-petit-grain","sable-fin"]', 'aggregats' => '["2"]', 'photo' => 'https://ouego-dashboard.adjemin.com/documents/carriers/01KCT32KVMBW6QFK44V9432RBS.jpeg', 'created_at' => '2025-11-27 14:56:41', 'updated_at' => '2025-12-19 01:20:06', 'deleted_at' => null],
            ['name' => 'Carrière Agban CEFAL', 'phone' => null, 'location_latitude' => 5.3261914, 'location_longitude' => -3.857676, 'is_active' => true, 'products' => '["sable-gros-grain","sable-petit-grain","sable-fin"]', 'aggregats' => '["2"]', 'photo' => null, 'created_at' => '2025-10-18 23:51:20', 'updated_at' => '2026-03-14 11:45:19', 'deleted_at' => null],
            ['name' => 'Carrière démo', 'phone' => null, 'location_latitude' => 5.388558, 'location_longitude' => -3.99259, 'is_active' => false, 'products' => '["sable-gros-grain","sable-fin","sable-petit-grain","gravier-515-petit-grain","gravier-525-melange","gravier-1525-gros-grain"]', 'aggregats' => '["2","1"]', 'photo' => null, 'created_at' => '2026-01-09 14:29:08', 'updated_at' => '2026-02-12 11:44:09', 'deleted_at' => null],
            ['name' => 'Test Carrière', 'phone' => null, 'location_latitude' => 5.3659301, 'location_longitude' => -3.9470805, 'is_active' => false, 'products' => '["sable-fin","sable-petit-grain","sable-gros-grain"]', 'aggregats' => '["2"]', 'photo' => null, 'created_at' => '2025-12-23 01:05:33', 'updated_at' => '2026-03-16 11:56:46', 'deleted_at' => null],
        ];

        foreach ($carriers as $carrier) {
            DB::table('carriers')->updateOrInsert(
                ['name' => $carrier['name']],
                $carrier
            );
        }
    }
}
