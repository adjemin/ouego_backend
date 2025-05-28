<?php 

namespace App\Services;

use App\Models\Carrier;
use App\Models\Order;
use App\Models\ZoneMapping;
use App\Utilities\GoogleMapsAPIUtils;

class OrderAssignmentV2Service {

    private $maxDistance = 5; // Distance maximale en km
    private $maxDrivers = 10; // Nombre maximum de chauffeurs à retourner
    
    public function searchNearDriverByCarrier(float $longitude, float $latitude){
        
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

            // Calculer la distance entre le chauffeur et la carriere
            $distance = GoogleMapsAPIUtils::distanceHaversine(
                $longitude,
                $latitude,
                $carrier->location_latitude,
                $carrier->location_longitude
            );

            $distance = ceil($distance);


            if(($nearCarrier->isEmpty() || $nearCarrier['distance'] > $distance) ){
                $nearCarrier = [
                    'carrier' => $carrier,
                    'distance' => $distance." km",
                ];
            }
        }

        if(empty($nearCarrier)){
            throw new \Exception("Aucune carrière trouvée");
        }

        // Rechercher les chauffeurs de la carriere la plus proche
        $drivers = $nearCarrier['carrier']->drivers()->where(["is_active" => true, 'is_available' => true])->get();

        if ($drivers->isEmpty()) {
            throw new \Exception("Aucun chauffeur trouvé");
        }


        // Filtrer les chauffeurs par distance
        $drivers = $drivers->filter(function ($driver) use ($carrier) {
            $distance = GoogleMapsAPIUtils::distanceHaversine(
                $carrier->location_latitude, 
                $carrier->location_longitude,
                $driver->last_location_latitude, 
                $driver->last_location_longitude
            );

            $driver->distance = ceil($distance);

            return $distance <= $this->maxDistance;
        });

        if ($drivers->isEmpty()) {
            throw new \Exception("Aucun chauffeur trouvé à moins de $this->maxDistance Km de la carriere");
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
            throw new \Exception("Aucun chauffeur disponible sans course en cours");
        }

        // Trier par distance croissante, puis nombre de commandes croissant, puis note décroissante
        $drivers = $drivers
            ->sortBy([
                ['distance', 'asc'],
                ['current_orders', 'asc'],
                ['rate', 'desc']
            ])
            ->take($this->maxDrivers)
            ->values();

        $data = [
            "carrier_info" => $nearCarrier,
            "drivers" => $drivers->toArray()
        ];

        return $data;
    }

}   