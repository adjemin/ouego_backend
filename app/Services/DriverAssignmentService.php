<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Models\DriverNotification;
use App\Models\RoutePoint;
use App\Utilities\DriverNotificationsUtils;
use App\Utilities\GoogleMapsAPIUtils;
use App\Events\OrderAssigned;
use App\Models\Carrier;
use App\Models\DriverCarrier;
use Illuminate\Support\Facades\Log;


class DriverAssignmentService
{

    // Ville de référence : Abidjan
    // private float $villeLat = 5.3252258;
    // private float $villeLng = -4.019603;
    // private float $rayonMaxKm = 50;
    // private int $maxUpdateTime  = 30;
    private int $maxDrivers = 5;
    

    /**
     * Assigne une course au chauffeur le plus proche disponible.
     *

     * @param Order $order
     * @param int $distance mètre
     * @return Driver|null Le chauffeur assigné ou null si aucun n'est disponible
     */
    public function assignCourseAndLocationNearestDriver($order,  $distance = null)
    {

        $route_point = RoutePoint::where([
            'order_id' => $order->id,
            'type' => 'source'
        ])->first();

        if (!$route_point) {
            $route_point = RoutePoint::where([
                'order_id' => $order->id
            ])->first();
        }

        
        if($route_point != null){
            $nearestDrivers = $this->findCourseAndLocationNearestDrivers($order->service_slug, $route_point->latitude, $route_point->longitude, $this->maxDrivers, $distance);

            if ($nearestDrivers->isEmpty()) {
                return null;
            }

            // $driver = $nearestDrivers;

            // Ici, vous pouvez ajouter la logique pour assigner effectivement la course au chauffeur
            // Par exemple, mettre à jour le statut du chauffeur, créer un enregistrement de course, etc.
            foreach($nearestDrivers as $driver){
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
                }
    
                // Déclencher l'événement d'assignation de commande
                event(new OrderAssigned($orderInvitation));
            }

            return $driver;


        }else{
            return null;
        }

    }

    public function getAggregatDriverAndNotify(Order $order, $limit = 5, $maxDistance = null): array
    {
        try {

            // Validation des données d'entrée
            if (!$order || !$order->exists) {
                Log::error("getDriverAndNotify: Commande invalide", ['order_id' => $order?->id]);
                return [
                    'success' => false,
                    'message' => 'Commande invalide',
                    'data' => null
                ];
            }


            Log::info("Début de recherche de chauffeurs", [
                'order_id' => $order->id,
                'limit' => $limit,
                'max_distance' => $maxDistance
            ]);

            // Vérification du produit associé
            $product = $order->items->first();
            if (!$product || !$product->carrier_id) {
                Log::error("getDriverAndNotify: Aucun produit ou carrier_id manquant", [
                    'order_id' => $order->id,
                    'product_exists' => !!$product
                ]);
                return [
                    'success' => false,
                    'message' => 'Produit ou transporteur non trouvé pour cette commande',
                    'data' => null
                ];
            }

            // Recherche des chauffeurs avec l'algorithme de pondération
            $driversData = $this->aggregatExpressOrderAssignment(
                $product->carrier_id, 
                $order->id, 
                $order->service_slug, 
                $limit, 
                $maxDistance
            );

    
            // Vérification que des chauffeurs ont été trouvés
            if (empty($driversData)) {
                Log::warning("getDriverAndNotify: Aucun chauffeur trouvé", [
                    'order_id' => $order->id,
                    'carrier_id' => $product->carrier_id,
                    'service_slug' => $order->service_slug
                ]);
                return [
                    'success' => false,
                    'message' => 'Aucun chauffeur disponible trouvé',
                    'data' => [
                        'drivers_searched' => 0,
                        'carrier_id' => $product->carrier_id
                    ]
                ];
            }

            $nearestDriverIds = array_column($driversData, 'driver_id');
            $carrier_id =$product->carrier_id;

            Log::info("Chauffeurs trouvés, création de la demande de course", [
                'order_id' => $order->id,
                'drivers_count' => count($nearestDriverIds),
                'carrier_id' => $carrier_id
            ]);

            foreach($nearestDriverIds as $driverId){
                $orderInvitation = OrderInvitation::where([
                    'driver_id' => $driverId,
                    'order_id' => $order->id,
                ])->first();
    
                if($orderInvitation == null){
                    $orderInvitation = OrderInvitation::create([
                        'driver_id' => $driverId,
                        'order_id' => $order->id,
                        'is_waiting_acceptation' => true,
                        'acceptation_time' => null,
                        'rejection_time' => null,
                        'latitude' => null,
                        'longitude' => null
                    ]);
                }
    
                // Déclencher l'événement d'assignation de commande
                event(new OrderAssigned($orderInvitation));
            }

            return $driversData;

        } catch (\Exception $e) {
            Log::error("Erreur dans getDriverAndNotify", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de la recherche de chauffeurs: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }  
    
    // **************** Fonction de recherche du chauffeur le plus proche **************** //

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
    public function findCourseAndLocationNearestDrivers($service_slug, $latitude, $longitude, $limit = 5, $maxDistance = null)
    {
        $maxUpdateTime  = 30;
        // Utilisation de l'index R-Tree de PostgreSQL pour une recherche efficace
        $query = Driver::select('drivers.*')
            ->selectRaw('ST_Distance(last_location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance', [$longitude, $latitude])
            ->whereRaw('is_available = true')
            ->whereRaw('is_active = true')
            ->whereRaw("updated_at >= NOW() - INTERVAL '{$maxUpdateTime} MINUTE'")
            ->whereJsonContains('services', $service_slug)
            ->orderByRaw('last_location <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography', [$longitude, $latitude]);

        if ($maxDistance) {
            $query->whereRaw('ST_DWithin(last_location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$longitude, $latitude, $maxDistance]);
        }

        return $query->limit($limit)->get();
    }



    public function aggregatExpressOrderAssignment($carrier_id, $order_id, $service_slug, $limit = 5, $maxDistance = null): array
    {

        $carrier = Carrier::find($carrier_id);
        if (!$carrier) {
            throw new \Exception("Carrière non trouvée");
        }
        $longitude = $carrier->location_longitude;
        $latitude = $carrier->location_latitude;

        $route_point = RoutePoint::where([
            'order_id' => $order_id,
            'type' => 'source'
        ])->first();

        if (!$carrier) {
            throw new \Exception("Carrière non trouvée");
        }

        $driverIds = DriverCarrier::where('carrier_id', $carrier_id)->distinct('driver_id')->pluck('driver_id')->toArray();
        
        $query = Driver::select('drivers.*')
            ->whereIn('id', $driverIds)
            ->selectRaw('ST_Distance(last_location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance', [$longitude, $latitude])
            ->whereRaw('is_available = true')
            ->whereRaw('is_active = true')
            // ->whereRaw("updated_at >= NOW() - INTERVAL '{$this->maxUpdateTime} MINUTE'")
            ->whereJsonContains('services', $service_slug)
            ->orderByRaw('last_location <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography', [$longitude, $latitude]);

        if ($maxDistance) {
            $query->whereRaw('ST_DWithin(last_location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$longitude, $latitude, $maxDistance]);
        }

        $chauffeursProches = $query->get();

        // Étape 5 : Calcul des pondérations
        $ponderations = [];
        $minDistanceChauffeurCarriere = $chauffeursProches->min('distance');
        $maxJetons = $chauffeursProches->max('current_balance');
        $maxJetons = $maxJetons > 0 ? $maxJetons : 1;
        $chauffeursCarriere = count($driverIds);


        foreach ($chauffeursProches as $item) {
            $chauffeur = $item;
            $distanceChauffeurCarriere = $item->distance;
            $distanceCarriereLivraisonData = GoogleMapsAPIUtils::getDistance(
                    [$route_point->latitude,
                    $route_point->longitude],
                    [$chauffeur->last_location_latitude,
                    $chauffeur->last_location_longitude]
                );
            $distanceCarriereLivraison = $distanceCarriereLivraisonData['distance']['value'] ?? 1;

            $score = [];

            $score['proximity_driver_carrier'] = number_format(($minDistanceChauffeurCarriere / $distanceChauffeurCarriere) * 100, 2);
            $score['jetons'] = number_format((floatval($chauffeur->current_balance) / ($maxJetons ?? 1)) * 100, 2);
            $score['proximity_carrier_delivery'] = number_format(($distanceCarriereLivraison / $distanceCarriereLivraison) * 100, 2);
            $score['note'] = number_format(($chauffeur->rate / 5) * 100, 2);
            $score['concentration'] = number_format(($chauffeursCarriere / $chauffeursCarriere) * 100, 2);

            $scoreTotal = number_format(
                $score['proximity_driver_carrier'] * 0.30 +
                $score['jetons'] * 0.25 +
                $score['proximity_carrier_delivery'] * 0.25 +
                $score['note'] * 0.15 +
                $score['concentration'] * 0.05,
                3
            );

            $ponderations[$chauffeur->id] = [
                'driver_id' => $chauffeur->id,
                'carrier_id' => $carrier->id,
                'distance' => $item['distance'],
                'score_total' => $scoreTotal,
                'details' => $score,
            ];
        }

        $ponderations = collect($ponderations)->sortByDesc('score_total')->values()->take($limit)->all();

        if (empty($ponderations)) {
            throw new \Exception("Aucun chauffeur trouvé à proximité de la carrière");
        }

        return $ponderations;
    }

}
