<?php 

namespace App\Utilities;

use App\Models\Carrier;
use App\Models\Order;
use App\Models\ZoneMapping;

class AlgoMain{

    public static function findNearCarrier(float $longitude, float $latitude){
        
        $zone = GoogleMapsAPIUtils::trouverZoneParPointPostGIS($longitude, $latitude);
        if(empty($zone)){
            throw new \Exception("Zone introuvable");
        }

        $zoneMapping = ZoneMapping::where('zone_id', $zone->id)->get();
        if(empty($zoneMapping)){
            throw new \Exception("ZoneMapping introuvable");
        }

        $carrierIds = $zoneMapping->pluck('carrier_id')->toArray();

        $carriers = Carrier::whereIn('id', $carrierIds)->get();
        if(empty($carrier)){
            throw new \Exception("Aucune carrière trouvée");
        }

        $nearCarrier = [];

        // Rechercher la carriere la plus proche
        foreach($carriers as $carrier){

            $result = GoogleMapsAPIUtils::getDistance([
                $longitude,
                $latitude,
            ],[
                $carrier->location_latitude,
                $carrier->location_longitude
            ]);

            $distance = $result['distance']['value'];

            if(empty($nearCarrier) || $distance < $nearCarrier['distance']){
                $nearCarrier = [
                    'carrier' => $carrier,
                    'distance' => $distance,
                    'duration' => $result['duration']['value'],
                ];
            }
        }


        return $nearCarrier;
    }

    public static function searchNearDriverByCarrier(Carrier $carrier, $max_distance = 5000, $max_drivers = 10){
        $drivers = $carrier->drivers()->where('is_available', true)->with(['currentOrders', 'ratings'])->get();
        if(empty($drivers)){
            throw new \Exception("Aucun chauffeur trouvé");
        }

        // **************************************************************************
        // ETAPE 1 : On filtre les chauffeurs par distance
        // **************************************************************************
        foreach($drivers as $driver){

            // Calculer la distance entre le chauffeur et la carriere
            $result = GoogleMapsAPIUtils::getDistance([
                $carrier->location_longitude,
                $carrier->location_latitude,
                ],[
                    $driver->last_location_longitude,
                    $driver->last_location_latitude,
            ]);

            $distance = $result['distance']['value'];

            // On supprime le chauffeur si il est trop loin
            if($distance > $max_distance){
                $drivers->forget($driver->id);
            }

            $driver->distance = $distance;
        }
        
        //  Si aucun chauffeur n'est trouvé, on renvoie un message d'erreur
        if(empty($drivers)){
            throw new \Exception("Aucun chauffeur trouvé à moins de $max_distance m");
        }

        // On trie les chauffeurs par distance
        $drivers = $drivers->sortBy('distance');

        // **************************************************************************
        // ETAPE 2 : On filtre par courses en cours, notes et jetons
        // **************************************************************************
        
        $drivers = $drivers->orderBy('currentOrders', 'asc')
            ->orderBy('current_current_balance', 'desc')
            ->orderBy('ratings', 'desc')
            ->orderBy('distance', 'desc')
            ->take($max_drivers);
        
        return $drivers;
    }
}