<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Models\RoutePoint;
use App\Utilities\GoogleMapsAPIUtils;
use App\Events\OrderAssigned;
use App\Models\Carrier;
use App\Models\DriverCarrier;
use Illuminate\Support\Facades\Log;


class DriverEnSemaineAssignmentService
{

    private int $maxUpdateTime  = 30;
    

    /**
     * Assigne une course au chauffeur le plus proche disponible.
     *

     * @param Order $order
     * @param int $distance mètre
     * @return Driver|null Le chauffeur assigné ou null si aucun n'est disponible
     */
    public function assignCourseNearestDrivers($order,  $distance = null, $maxDrivers = 5)
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
            $nearestDrivers = $this->findCourseNearestDrivers($order->service_slug, $route_point->latitude, $route_point->longitude, $maxDrivers, $distance);

            Log::info("DriverEnSemaineAssignmentService: Commande course #{$order->id} - " . $nearestDrivers->count() . " chauffeurs trouvés à proximité ($distance km) pour assignation.");

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

    public function assignAggregatNearestDrivers(Order $order, $maxDistance = null, $limit = 5): array
    {
        try {

            // Validation des données d'entrée
            if (!$order || !$order->exists) {
                return [
                    'success' => false,
                    'message' => 'Commande invalide',
                    'data' => null
                ];
            }

            // Vérification du produit associé
            $product = $order->items->first();
            if (!$product || !$product->carrier_id) {
                return [
                    'success' => false,
                    'message' => 'Produit ou transporteur non trouvé pour cette commande',
                    'data' => null
                ];
            }


            // Recherche des chauffeurs avec l'algorithme de pondération
            $driversData = $this->findAggregatNearestDrivers($product->carrier_id, $order->id, $order->service_slug, $limit, $maxDistance);

            Log::info("DriverEnSemaineAssignmentService: Commande #{$order->id} - " . count($driversData) . " chauffeurs trouvés dans une distance de " . $maxDistance . " km pour l'agrégat.");
    
            // Vérification que des chauffeurs ont été trouvés
            if (empty($driversData)) {
               
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
    public function findCourseNearestDrivers($service_slug, $latitude, $longitude, $limit = 5, $maxDistance = null)
    {
        $isSaturday = now()->dayOfWeekIso === 6;
        $isFridayOrAfter = now()->dayOfWeekIso >= 5;
        $today = now()->toDateString();
        $rentalCancelledStatuses = [
            Order::CANCELLED, Order::CANCELLED_WITH_PAYMENT,
            Order::CANCELLED_BY_TAXI, Order::FAILED,
        ];

        // Utilisation de l'index R-Tree de PostgreSQL pour une recherche efficace
        $query = Driver::select('drivers.*')
            ->selectRaw('ST_Distance(last_location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance', [$longitude, $latitude])
            ->whereRaw('is_available = true')
            ->whereRaw('is_active = true')
            ->whereRaw("updated_at >= NOW() - INTERVAL '{$this->maxUpdateTime} MINUTE'")
            ->whereJsonContains('services', $service_slug)

            // 1) Limites chauffeur : trois courses en journée et cinq courses en semaine
           ->where(
                Order::selectRaw('count(*)')->whereColumn('drivers.id', 'orders.driver_id')->active()->day(),
                '<', 3
            )
            ->where(
                Order::selectRaw('count(*)')->whereColumn('drivers.id', 'orders.driver_id')->active()->week(),
                '<', 5
            );


            // 2) Règle spécifique du samedi (blocage total si semaine active)
            $query->when($isSaturday, fn($q) => $q->where(
                Order::selectRaw('count(*)')->whereColumn('drivers.id', 'orders.driver_id')->active()->week(),
                '=', 0
            ));

            // 3) Règle 3 + Exception Règle 4 pour en-semaine
            // Si vendredi ou après → blocage total (plus de courses en semaine admises)
            // Sinon → exception dernier jour : autorisé si location_end_date = aujourd'hui (dernier jour)
            if ($isFridayOrAfter) {
                $query->whereDoesntHave('orders', function ($q) use ($today, $rentalCancelledStatuses) {
                    $q->where('is_location', true)
                      ->whereNotIn('status', $rentalCancelledStatuses)
                      ->whereHas('orderItems', function ($sq) use ($today) {
                          $sq->where('location_start_date', '<=', $today)
                             ->where('location_end_date', '>=', $today);
                      });
                });
            } else {
                // Bloquer uniquement si la location n'est PAS à son dernier jour (end_date > today)
                $query->whereDoesntHave('orders', function ($q) use ($today, $rentalCancelledStatuses) {
                    $q->where('is_location', true)
                      ->whereNotIn('status', $rentalCancelledStatuses)
                      ->whereHas('orderItems', function ($sq) use ($today) {
                          $sq->where('location_start_date', '<=', $today)
                             ->where('location_end_date', '>', $today); // end_date > today = pas le dernier jour
                      });
                });
            }

            $query->orderByRaw('last_location <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography', [$longitude, $latitude]);


    
        if ($maxDistance) {
            $query->whereRaw('ST_DWithin(last_location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$longitude, $latitude, $maxDistance*1000]);
        }

        return $query->limit($limit)->get();
    }

    public function findAggregatNearestDrivers($carrier_id, $order_id, $service_slug, $limit = 5, $maxDistance = null): array
    {
        $isSaturday = now()->dayOfWeekIso === 6; 
        
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

        $isFridayOrAfter = now()->dayOfWeekIso >= 5;
        $today = now()->toDateString();
        $rentalCancelledStatuses = [
            Order::CANCELLED, Order::CANCELLED_WITH_PAYMENT,
            Order::CANCELLED_BY_TAXI, Order::FAILED,
        ];

        $query = Driver::select('drivers.*')
            ->whereIn('id', $driverIds)
            ->selectRaw('ST_Distance(last_location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance', [$longitude, $latitude])
            ->whereRaw('is_available = true')
            ->whereRaw('is_active = true')
            ->whereRaw("updated_at >= NOW() - INTERVAL '{$this->maxUpdateTime} MINUTE'")
            ->whereJsonContains('services', $service_slug)

            // 1) Limites chauffeur : trois courses en journée et cinq courses en semaine
            ->where(
                Order::selectRaw('count(*)')->whereColumn('drivers.id', 'orders.driver_id')->active()->day(),
                '<', 3
            )
            ->where(
                Order::selectRaw('count(*)')->whereColumn('drivers.id', 'orders.driver_id')->active()->week(),
                '<', 5
            )

            // 2) Règle spécifique du samedi (blocage total si semaine active)
            ->when($isSaturday, fn($q) => $q->where(
                Order::selectRaw('count(*)')->whereColumn('drivers.id', 'orders.driver_id')->active()->week(),
                '=', 0
            ));

        // 3) Règle 3 + Exception Règle 4 pour en-semaine
        if ($isFridayOrAfter) {
            $query->whereDoesntHave('orders', function ($q) use ($today, $rentalCancelledStatuses) {
                $q->where('is_location', true)
                  ->whereNotIn('status', $rentalCancelledStatuses)
                  ->whereHas('orderItems', function ($sq) use ($today) {
                      $sq->where('location_start_date', '<=', $today)
                         ->where('location_end_date', '>=', $today);
                  });
            });
        } else {
            $query->whereDoesntHave('orders', function ($q) use ($today, $rentalCancelledStatuses) {
                $q->where('is_location', true)
                  ->whereNotIn('status', $rentalCancelledStatuses)
                  ->whereHas('orderItems', function ($sq) use ($today) {
                      $sq->where('location_start_date', '<=', $today)
                         ->where('location_end_date', '>', $today);
                  });
            });
        }

        $query->orderByRaw('last_location <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography', [$longitude, $latitude]);

        // Pas de restriction de distance pour le gravier
        $orderItem = \App\Models\OrderItem::where('order_id', $order_id)->first();
        $isGravier = $orderItem
            && isset($orderItem->meta_data['product_slug'])
            && $orderItem->meta_data['product_slug'] === \App\Models\Product::GRAVIER_SLUG;

        if ($maxDistance && !$isGravier) {
            $query->whereRaw('ST_DWithin(last_location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$longitude, $latitude, $maxDistance*1000]);
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

            // Calcul de distance avec Haversine (calcul local, pas d'appel API)
            $distanceCarriereLivraisonKm = GoogleMapsAPIUtils::distanceHaversine(
                $route_point->latitude,
                $route_point->longitude,
                $chauffeur->last_location_latitude,
                $chauffeur->last_location_longitude
            );

            // Convertir en mètres pour cohérence
            $distanceCarriereLivraison = $distanceCarriereLivraisonKm * 1000;

            $score = [];

            $score['proximity_driver_carrier'] = number_format(($minDistanceChauffeurCarriere / $distanceChauffeurCarriere) * 100, 2);
            $score['jetons'] = number_format((floatval($chauffeur->current_balance) / ($maxJetons ?? 1)) * 100, 2);
            $score['proximity_carrier_delivery'] = number_format(($distanceCarriereLivraison / $distanceCarriereLivraison) * 100, 2);
            $score['note'] = number_format(($chauffeur->rate / 5) * 100, 2);
            $score['concentration'] = number_format(($chauffeursCarriere / $chauffeursCarriere) * 100, 2);

            $scoreTotal = number_format(
                floatval($score['proximity_driver_carrier']) * 0.30 +
                floatval($score['jetons']) * 0.25 +
                floatval($score['proximity_carrier_delivery']) * 0.25 +
                floatval($score['note']) * 0.15 +
                floatval($score['concentration']) * 0.05,
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
