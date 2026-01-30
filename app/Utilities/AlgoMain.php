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
        if(empty($carriers)){
            throw new \Exception("Aucune carrière trouvée");
        }


        $nearCarrier = collect([]);

        // Rechercher la carriere la plus proche
        foreach($carriers as $carrier){

            // Calculer la distance avec Haversine (calcul local, pas d'appel API)
            $distanceKm = GoogleMapsAPIUtils::distanceHaversine(
                $latitude,
                $longitude,
                $carrier->location_latitude,
                $carrier->location_longitude
            );

            // Convertir en mètres
            $distance = $distanceKm * 1000;

            // Estimation du temps de trajet (moyenne : 40 km/h en ville)
            $duration = ($distanceKm / 40) * 3600; // en secondes

            $nearCarrier = [
                'carrier' => $carrier,
                'distance' => $distance,
                'duration' => round($duration),
            ];
        }



        return $nearCarrier;
    }

    public static function searchNearDriverByCarrier(Carrier $carrier, int $maxDistance = 5, int $maxDrivers = 10): array
    {
        $drivers = $carrier->drivers()->where(["is_active" => true, 'is_available', true])->get();

        if ($drivers->isEmpty()) {
            throw new \Exception("Aucun chauffeur trouvé");
        }

        // Filtrer les chauffeurs par distance (Haversine - calcul local)
        $drivers = $drivers->filter(function ($driver) use ($carrier, $maxDistance) {
            $distanceKm = GoogleMapsAPIUtils::distanceHaversine(
                $carrier->location_latitude,
                $carrier->location_longitude,
                $driver->last_location_latitude,
                $driver->last_location_longitude
            );

            // Convertir en mètres
            $driver->distance = $distanceKm * 1000;

            return $distanceKm <= $maxDistance;
        });

        if ($drivers->isEmpty()) {
            throw new \Exception("Aucun chauffeur trouvé à moins de $maxDistance Km de la carriere");
        }

        // Écarter les chauffeurs qui ont des courses démarrées
        $drivers = $drivers->reject(function ($driver) {
            $orders = Order::where('driver_id', $driver->id)->where('is_completed', false)->get();
            $hasStarted = $orders->where('is_started', true)->isNotEmpty();

            if ($hasStarted) {
                return true;
            }

            $driver->current_orders = $orders->count();
            return false;
        });

        // Si tous les chauffeurs ont été écartés
        if ($drivers->isEmpty()) {
            throw new \Exception("Aucun chauffeur disponible sans course en cours");
        }

        // Trier par distance croissante, puis nombre de commandes croissant, puis note décroissante
        $drivers = $drivers
            ->sortBy([
                ['distance', 'asc'],
                ['current_orders', 'asc'],
                ['rate', 'desc']
            ])
            ->take($maxDrivers)
            ->values();

        return $drivers->toArray();
    }
}   