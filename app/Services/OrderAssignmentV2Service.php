<?php 

namespace App\Services;

use App\Models\Carrier;
use App\Models\Order;
use App\Models\ZoneMapping;
use App\Utilities\GoogleMapsAPIUtils;

class OrderAssignmentV2Service {

    private static $maxDistance = 30; // Distance maximale en km
    private static $maxDrivers = 10; // Nombre maximum de chauffeurs à retourner
    
    public static function searchNearDriverByCarrier(float $longitude, float $latitude){
        
        $zone = GoogleMapsAPIUtils::trouverZoneParPointPostGIS($longitude, $latitude);

        if(empty($zone)){
            throw new \Exception("Votre postion n'est pas converte par notre zone de livraison");
        }

        $zoneMapping = ZoneMapping::where('zone_id', $zone->id)->get();
        if(empty($zoneMapping)){
            throw new \Exception("Aucune carriere à proximité trouvée à cette zone");
        }


        $carrierIds = $zoneMapping->pluck('carrier_id')->toArray();

        $carriers = Carrier::whereIn('id', $carrierIds)->where('is_active', true)->get();
        if(empty($carriers)){
            throw new \Exception("Aucune carriere à proximité trouvée à cette zone");
        }

        $nearCarrier = collect([]);

        // Rechercher la carriere la plus proche
        foreach($carriers as $carrier){
            // Calculer la distance entre le chauffeur et la carriere (Haversine - calcul local)
            $distanceKm = GoogleMapsAPIUtils::distanceHaversine(
                $latitude,
                $longitude,
                $carrier->location_latitude,
                $carrier->location_longitude
            );

            // Convertir en mètres pour cohérence avec le reste du code
            $distanceMeters = $distanceKm * 1000;

            if($nearCarrier->count() == 0){
                $nearCarrier = collect([
                    'carrier' => $carrier,
                    'distance' => $distanceMeters,
                ]);
            }elseif($distanceMeters < $nearCarrier['distance']){
                $nearCarrier = [
                    'carrier' => $carrier,
                    'distance' => $distanceMeters,
                ];
            }
        }


        if(empty($nearCarrier)){
            throw new \Exception("Aucune carrière à proximité trouvée");
        }


        // Rechercher les chauffeurs de la carriere la plus proche
        $drivers = $nearCarrier['carrier']->drivers()->where(["is_active" => true, 'is_available' => true])->get();
        if ($drivers->isEmpty()) {
            throw new \Exception("Aucun chauffeur trouvé");
        }
        
        // Filtrer les chauffeurs par distance (Haversine - calcul local)
        $drivers = $drivers->filter(function ($driver) use ($nearCarrier) {
            $distanceKm = GoogleMapsAPIUtils::distanceHaversine(
                $nearCarrier['carrier']->location_latitude,
                $nearCarrier['carrier']->location_longitude,
                $driver->last_location_latitude,
                $driver->last_location_longitude
            );

            // Convertir en mètres pour cohérence
            $driver->distance = $distanceKm * 1000;

            return $distanceKm <= self::$maxDistance;
        });

        if ($drivers->isEmpty()) {
            throw new \Exception("Aucun chauffeur trouvé à moins de ".self::$maxDistance." Km de la carriere");
        }


        // Écarter les chauffeurs qui ont des courses démarrées
        $drivers = $drivers->reject(function ($driver) {
            $orders = Order::where('driver_id', $driver->id)->where(['is_draft' => false,  'is_completed' => false])->get();
            $hasStarted = $orders->where('is_started', true)->isNotEmpty();

            if ($hasStarted) {
                return true;
            }

            $driver->current_orders = $orders->count();
            return false;
        });

        // Si tous les chauffeurs ont été écartés
        if ($drivers->isEmpty()) {
            throw new \Exception("Aucun chauffeur disponible pour le moment");
        }

        // Trier par distance croissante, puis nombre de commandes croissant, puis note décroissante
        $drivers = $drivers
            ->sortBy([
                ['distance', 'asc'],
                ['current_orders', 'asc'],
                ['rate', 'desc']
            ])
            ->take(self::$maxDrivers)
            ->values();

        $data = [
            "carrier_info" => $nearCarrier,
            "drivers" => $drivers->toArray()
        ];

        return $data;
    }

}   