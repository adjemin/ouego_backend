<?php

namespace App\Utilities;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\Zone;

class GoogleMapsAPIUtils
{

    #const  GOOGLE_MAP_API_KEY = "AIzaSyAP8YmQymC20lzQgLrTWfLznDw4p3tnn-g";

    /**
     * Calcule la distance entre deux points en utilisant l'API Google Maps Distance Matrix.
     * ATTENTION : Cette méthode consomme l'API Google Maps. Préférez distanceHaversine() pour les calculs internes.
     * Utilisez cette méthode uniquement pour afficher la distance routière précise au client final.
     *
     * @param array $origins [latitude, longitude]
     * @param array $destinations [latitude, longitude]
     * @return array
     */
    public static function getDistance(array $origins,array $destinations){
        // Créer une clé de cache unique basée sur les coordonnées
        $cacheKey = sprintf(
            'google_distance_%s_%s_%s_%s',
            round($origins[0], 4),
            round($origins[1], 4),
            round($destinations[0], 4),
            round($destinations[1], 4)
        );

        // Tenter de récupérer depuis le cache (valide 24h)
        return Cache::remember($cacheKey, 86400, function() use ($origins, $destinations) {
            $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                'origins' => $origins[0].','.$origins[1],
                'destinations' => $destinations[0].','.$destinations[1],
                'language' => 'en',
                'mode' => 'driving',
                'key'=> env('GOOGLE_MAP_API_KEY',"AIzaSyAP8YmQymC20lzQgLrTWfLznDw4p3tnn-g")
            ]);

            $res = $response->json();
            $rows  = $res['rows'];

            if(count($rows) >0){
                $row = $rows[0];
                if(array_key_exists('elements', $row)){
                    $elements = $row['elements'];
                    if(count($elements) > 0 ){
                        return  $elements[0];
                    }
                }
            }

            return [];
        });

    }

    /**
     * Trouve une zone contenant un point donné en utilisant PostGIS.
     *
     * @param float $longitude Longitude du point
     * @param float $latitude Latitude du point
     * @return Zone|null Retourne la zone contenant le point, ou null si aucune zone n'est trouvée
     */
    public static function trouverZoneParPointPostGIS(float $longitude, float $latitude): ?Zone
    {
        return Zone::whereRaw(
            'ST_Contains(geometry::geometry, ST_SetSRID(ST_MakePoint(?, ?), 4326))',
            [$longitude, $latitude]
        )->first();
    }



    function trouverZoneParPointGoogleMaps(float $latitude, float $longitude): ?string
    {
        $apiKey = env('GOOGLE_MAP_API_KEY', 'AIzaSyAP8YmQymC20lzQgLrTWfLznDw4p3tnn-g');
        $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$latitude},{$longitude}&key={$apiKey}";
    
        $response = Http::get($url);
    
        if ($response->successful()) {
            $results = $response->json('results');
    
            if (!empty($results)) {
                // Parcours les adresses retournées pour trouver la "locality" ou "sublocality"
                foreach ($results as $result) {
                    foreach ($result['address_components'] as $component) {
                        if (in_array('locality', $component['types']) || in_array('sublocality', $component['types'])) {
                            return $component['long_name']; // Exemple: "Yopougon"
                        }
                    }
                }
            }
        }
    
        return null;
    }

    /**
     * Calcule la distance à vol d'oiseau entre deux points en utilisant la formule de Haversine.
     * Cette méthode est rapide, ne consomme pas d'API, et est suffisamment précise (~0.5%) pour
     * le matching de chauffeurs et les calculs internes.
     *
     * @param float $lat1 Latitude du point 1
     * @param float $lon1 Longitude du point 1
     * @param float $lat2 Latitude du point 2
     * @param float $lon2 Longitude du point 2
     * @return float Distance en kilomètres
     */
    public static function distanceHaversine($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) ** 2 +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
    
}
