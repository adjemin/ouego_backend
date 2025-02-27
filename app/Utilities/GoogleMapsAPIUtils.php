<?php

namespace App\Utilities;

use Illuminate\Support\Facades\Http;

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
}
