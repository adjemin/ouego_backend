<?php

namespace App\Utilities;

use Illuminate\Support\Facades\Http;
use App\Models\Zone;

class GoogleMapsAPIUtils
{

    #const  GOOGLE_MAP_API_KEY = "AIzaSyCNZfIwGs9Y1hlRDCyiw3LV8dpLu1biIbM";

    /**
     * @param array $origins
     * @param array $destinations
     * @return array
     */
    public static function getDistance(array $origins,array $destinations){

        $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json', [
            'destinations' => $destinations[0].','.$destinations[1],
            'origins' => $origins[0].','.$origins[1],
            'language' => 'en',
            'mode' => 'driving',
            'key'=> env('GOOGLE_MAP_API_KEY',"AIzaSyCNZfIwGs9Y1hlRDCyiw3LV8dpLu1biIbM")
        ]);

        $res = $response->json();
        $rows  = $res['rows'];

        if(count($rows) >0){
            $row = $rows[0];
            if(array_key_exists('elements', $row)){
                $elements = $row['elements'];
                if(count($elements) >0){
                    return  $elements[0];
                }
            }
        }

        /**
         *
            {
                "distance": {
                    "text": "191 km",
                    "value": 191416
                },
                "duration": {
                    "text": "2 hours 18 mins",
                    "value": 8278
                },
                "status": "OK"
            }
         */

        return [];
    }

    public static function trouverZoneParPointPostGIS(float $longitude, float $latitude): ?Zone
    {
    
        return Zone::whereRaw('ST_Contains(geom::geometry, ST_SetSRID(ST_MakePoint(?, ?)::geometry, 4326))', [$longitude, $latitude])
        ->first();
    }



    function trouverZoneParPointGoogleMaps(float $latitude, float $longitude): ?string
    {
        $apiKey = env('GOOGLE_MAPS_API_KEY'); // Stocké dans ton fichier .env
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

    static function distanceHaversine($lat1, $lon1, $lat2, $lon2)
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
