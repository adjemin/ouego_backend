<?php

namespace App\Services;

use App\Models\Carrier;
use App\Models\Driver;
use App\Models\DriverCarrier;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RoutePoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Recherche et invitation des chauffeurs les plus proches, commune à tous les types de livraison.
 *
 * Règles appliquées à tous les types :
 *  - chauffeur disponible, actif, localisé depuis moins de 30 minutes, qui propose le service ;
 *  - pas d'invitation déjà refusée ou close pour la commande ;
 *  - moins de trois courses en journée et moins de cinq courses en semaine en cours ;
 *  - le samedi, aucune course en semaine en cours ;
 *  - aucune location en cours aujourd'hui.
 *
 * Chaque type de livraison ajoute ses propres règles dans applyDeliveryTypeRules().
 */
abstract class NearestDriverAssignmentService
{
    protected int $maxUpdateTime = 30; // minutes

    private const RENTAL_CANCELLED_STATUSES = [
        Order::CANCELLED,
        Order::CANCELLED_WITH_PAYMENT,
        Order::CANCELLED_BY_TAXI,
        Order::FAILED,
    ];

    /**
     * Règles propres au type de livraison, ajoutées aux règles communes.
     */
    protected function applyDeliveryTypeRules(Builder $query): void
    {
    }

    /**
     * Opérateur appliqué à location_end_date pour qu'une location en cours bloque le chauffeur :
     * « >= » bloque aussi le dernier jour de la location.
     */
    protected function blockingRentalEndOperator(): string
    {
        return '>=';
    }

    /**
     * Invite les chauffeurs les plus proches du point de départ d'une course.
     *
     * @param int|null $distance Rayon de recherche en kilomètres
     * @return Driver|null Le dernier chauffeur invité, ou null si aucun n'est disponible
     */
    public function assignCourseNearestDrivers($order, $distance = null, $maxDrivers = 5)
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

        if ($route_point == null) {
            return null;
        }

        $nearestDrivers = $this->findCourseNearestDrivers($order->service_slug, $route_point->latitude, $route_point->longitude, $maxDrivers, $distance, $order->id);
        Log::info($this->logPrefix().": Commande course #{$order->id} - " . $nearestDrivers->count() . " chauffeurs trouvés à proximité ($distance km) pour assignation.");

        if ($nearestDrivers->isEmpty()) {
            return null;
        }

        foreach ($nearestDrivers as $driver) {
            OrderInvitation::inviteDriver($order->id, $driver);
        }

        return $driver;
    }

    /**
     * Invite les chauffeurs les mieux classés pour livrer un agrégat depuis sa carrière.
     *
     * @param int|null $maxDistance Rayon de recherche en kilomètres (ignoré pour le gravier et le ciment)
     */
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

            Log::info($this->logPrefix().": Commande #{$order->id} - " . count($driversData) . " chauffeurs trouvés dans une distance de " . $maxDistance . " km pour l'agrégat.");

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

            foreach (array_column($driversData, 'driver_id') as $driverId) {
                $driver = Driver::find($driverId);
                if ($driver) {
                    OrderInvitation::inviteDriver($order->id, $driver);
                }
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

    /**
     * Trouve les chauffeurs éligibles les plus proches d'une position, du plus proche au plus éloigné.
     *
     * @param int|null $maxDistance Rayon de recherche en kilomètres (optionnel)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findCourseNearestDrivers($service_slug, $latitude, $longitude, $limit = 5, $maxDistance = null, ?int $orderId = null)
    {
        $query = $this->eligibleDriversQuery($service_slug, $latitude, $longitude, $orderId);

        if ($maxDistance) {
            $this->withinDistance($query, $latitude, $longitude, $maxDistance);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Classe les chauffeurs éligibles rattachés à la carrière (AggregatDriverScoringService).
     *
     * @param int|null $maxDistance Rayon de recherche autour de la carrière en kilomètres (ignoré pour le gravier et le ciment)
     * @throws \Exception Si la carrière n'existe pas ou si aucun chauffeur n'est trouvé
     */
    public function findAggregatNearestDrivers($carrier_id, $order_id, $service_slug, $limit = 5, $maxDistance = null): array
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

        $driverIds = DriverCarrier::where('carrier_id', $carrier_id)->distinct('driver_id')->pluck('driver_id')->toArray();

        $query = $this->eligibleDriversQuery($service_slug, $latitude, $longitude, $order_id)
            ->whereIn('id', $driverIds);

        // Pas de restriction de distance pour le gravier et le ciment
        $orderItem = OrderItem::where('order_id', $order_id)->first();
        $isGravier = $orderItem
            && isset($orderItem->meta_data['product_slug'])
            && in_array($orderItem->meta_data['product_slug'], [
                Product::GRAVIER_SLUG,
                Product::CIMENT_SLUG,
            ]);

        if ($maxDistance && !$isGravier) {
            $this->withinDistance($query, $latitude, $longitude, $maxDistance);
        }

        $chauffeursProches = $query->get();

        // Classement des chauffeurs par score
        $ponderations = app(AggregatDriverScoringService::class)->rank($chauffeursProches, $carrier, $route_point, $limit);

        if (empty($ponderations)) {
            throw new \Exception("Aucun chauffeur trouvé à proximité de la carrière");
        }

        return $ponderations;
    }

    /**
     * Chauffeurs éligibles, triés du plus proche au plus éloigné de la position (index GIST sur last_location).
     */
    protected function eligibleDriversQuery($service_slug, $latitude, $longitude, ?int $orderId): Builder
    {
        $today = now()->toDateString();
        $rentalEndOperator = $this->blockingRentalEndOperator();

        $query = Driver::select('drivers.*')
            ->selectRaw('ST_Distance(last_location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance', [$longitude, $latitude])
            ->whereRaw('is_available = true')
            ->whereRaw('is_active = true')
            ->whereRaw("updated_at >= NOW() - INTERVAL '{$this->maxUpdateTime} MINUTE'")
            ->whereJsonContains('services', $service_slug)
            ->withoutClosedInvitationFor($orderId)

            // Moins de trois courses en journée et moins de cinq courses en semaine en cours
            ->where($this->activeOrderCount('day'), '<', 3)
            ->where($this->activeOrderCount('week'), '<', 5)

            // Le samedi, blocage total si une course en semaine est en cours
            ->when(now()->dayOfWeekIso === 6, fn ($q) => $q->where($this->activeOrderCount('week'), '=', 0))

            // Règle 3 : blocage si une location est en cours aujourd'hui
            ->whereDoesntHave('orders', function ($q) use ($today, $rentalEndOperator) {
                $q->where('is_location', true)
                  ->whereNotIn('status', self::RENTAL_CANCELLED_STATUSES)
                  ->whereHas('orderItems', function ($sq) use ($today, $rentalEndOperator) {
                      $sq->where('location_start_date', '<=', $today)
                         ->where('location_end_date', $rentalEndOperator, $today);
                  });
            })

            ->orderByRaw('last_location <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography', [$longitude, $latitude]);

        $this->applyDeliveryTypeRules($query);

        return $query;
    }

    /**
     * Sous-requête : nombre de commandes en cours du chauffeur pour un type de livraison (scope de Order).
     */
    protected function activeOrderCount(string $deliveryTypeScope): Builder
    {
        return Order::selectRaw('count(*)')
            ->whereColumn('drivers.id', 'orders.driver_id')
            ->active()
            ->{$deliveryTypeScope}();
    }

    private function withinDistance(Builder $query, $latitude, $longitude, $maxDistance): void
    {
        $query->whereRaw('ST_DWithin(last_location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$longitude, $latitude, $maxDistance * 1000]);
    }

    private function logPrefix(): string
    {
        return class_basename(static::class);
    }
}
