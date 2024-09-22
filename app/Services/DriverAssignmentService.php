<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Driver;

class DriverAssignmentService
{
    /**
     * Trouve les chauffeurs les plus proches d'une localisation donnée.
     *
     * @param string $service_slug
     * @param float $latitude Latitude de la localisation de départ
     * @param float $longitude Longitude de la localisation de départ
     * @param int $limit Nombre maximum de chauffeurs à retourner
     * @param float $maxDistance Distance maximum en mètres (optionnel)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findNearestDrivers($service_slug, $latitude, $longitude, $limit = 5, $maxDistance = null)
    {
        // Utilisation de l'index R-Tree de PostgreSQL pour une recherche efficace
        $query = Driver::select('drivers.*')
        ->selectRaw('ST_Distance(last_location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance', [$longitude, $latitude])
        ->whereRaw('is_available = true')
        ->whereRaw('is_active = true')
        ->whereJsonContains('services', $service_slug)
        ->orderByRaw('last_location <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography', [$longitude, $latitude]);

        if ($maxDistance) {
            $query->whereRaw('ST_DWithin(last_location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$longitude, $latitude, $maxDistance]);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Assigne une course au chauffeur le plus proche disponible.
     *

     * @param Order $order
     * @return Driver|null Le chauffeur assigné ou null si aucun n'est disponible
     */
    public function assignNearestDriver($order)
    {

        $route_point = RoutePoint::where([
            'order_id' => $order->id,
            'type' => 'source'
        ])->first();

        if($route_point != null){
            $nearestDrivers = $this->findNearestDrivers($order->service_slug, $route_point->latitude, $route_point->longitude, 1, $distance);

            if ($nearestDrivers->isEmpty()) {
                return null;
            }

            $driver = $nearestDrivers->first();

            // Ici, vous pouvez ajouter la logique pour assigner effectivement la course au chauffeur
            // Par exemple, mettre à jour le statut du chauffeur, créer un enregistrement de course, etc.

            $orderInvitation = OrderInvitation::where([
                'driver_id' => $driver->id,
                'order_id' => $order->id,
            ])->first();

            if($orderInvitation == null){
                $orderInvitation = OrderInvitation::create([
                    'driver_id' => $driver->id,
                    'order_id' => $order->id,
                    'is_waiting_acceptation' => true,
                    'acceptation_time' => null,
                    'rejection_time' => null,
                    'latitude' => null,
                    'longitude' => null
                ]);

                //Push Notification
                $driverNotification = DriverNotification::create([
                    'driver_id' => $driver->id,
                    'title' => 'Course #'.$order->id." vous a été affectée",
                    'subtitle' => "Acceptez ou Refusez la course",
                    'data_id' => $orderInvitation->id,
                    'type' => $orderInvitation->table,
                    'is_read' => false,
                    'is_received' => false,
                    'meta_data' => null
                ]);
                DriverNotificationsUtils::notify($driverNotification);
            }

            return $driver;


        }else{

            return null;

        }

    }
}
