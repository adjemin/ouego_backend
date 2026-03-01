<?php

namespace App\Services;

use App\Events\OrderAssigned;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Models\RoutePoint;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DriverLocationAssignmentService
{
    private int $maxUpdateTime = 30;
    private int $maxDrivers = 5;

    /**
     * Point d'entrée principal : trouve les chauffeurs éligibles pour une commande de location
     * et crée les invitations.
     */
    public function findEligibleDriversForLocation(Order $order, int $limit = null): array
    {
        $limit = $limit ?? $this->maxDrivers;

        $item = $order->orderItems()->where('service_slug', 'location')->first();

        if (!$item) {
            Log::warning("DriverLocationAssignmentService: Aucun order_item location trouvé pour commande #{$order->id}");
            return [];
        }

        $startDate = $item->location_start_date;
        $endDate   = $item->location_end_date;

        if (!$startDate || !$endDate) {
            Log::warning("DriverLocationAssignmentService: Dates de location manquantes pour commande #{$order->id}");
            return [];
        }

        $routePoint = RoutePoint::where('order_id', $order->id)
            ->where('type', 'source')
            ->first()
            ?? RoutePoint::where('order_id', $order->id)->first();

        if (!$routePoint) {
            Log::warning("DriverLocationAssignmentService: Aucun route_point trouvé pour commande #{$order->id}");
            return [];
        }

        $isUrgent = Carbon::parse($startDate)->diffInHours(now(), false) > -24
                 && Carbon::parse($startDate)->isFuture();

        $drivers = $this->queryEligibleDrivers(
            $order->service_slug,
            $routePoint->latitude,
            $routePoint->longitude,
            $startDate,
            $endDate,
            $limit,
            $isUrgent
        );

        Log::info("DriverLocationAssignmentService: Commande #{$order->id} ({$startDate} → {$endDate}) "
            . "— {$drivers->count()} chauffeur(s) éligible(s) trouvé(s) [urgent=" . ($isUrgent ? 'oui' : 'non') . "]");

        foreach ($drivers as $driver) {
            $invitation = OrderInvitation::firstOrCreate(
                ['driver_id' => $driver->id, 'order_id' => $order->id],
                [
                    'is_waiting_acceptation' => true,
                    'acceptation_time'       => null,
                    'rejection_time'         => null,
                    'latitude'               => null,
                    'longitude'              => null,
                ]
            );

            event(new OrderAssigned($invitation));
        }

        return $drivers->pluck('id')->toArray();
    }

    /**
     * Requête de recherche des chauffeurs éligibles pour une location.
     *
     * Règles appliquées :
     *  - is_available + is_active + ping récent
     *  - service 'location' dans le JSON services
     *  - aucune location chevauchant [startDate, endDate] (Règle 2)
     *  - si urgent (<24h) : priorité aux chauffeurs sans location active aujourd'hui,
     *    puis tri par count cours en-semaine croissant (Règle 5)
     *  - sinon : tri par distance croissante
     */
    private function queryEligibleDrivers(
        string $serviceSlug,
        float $latitude,
        float $longitude,
        string $startDate,
        string $endDate,
        int $limit,
        bool $isUrgent
    ) {
        $cancelledStatuses = [
            Order::CANCELLED,
            Order::CANCELLED_WITH_PAYMENT,
            Order::CANCELLED_BY_TAXI,
            Order::FAILED,
        ];

        $query = Driver::select('drivers.*')
            ->selectRaw(
                'ST_Distance(last_location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance',
                [$longitude, $latitude]
            )
            ->whereRaw('is_available = true')
            ->whereRaw('is_active = true')
            ->whereRaw("updated_at >= NOW() - INTERVAL '{$this->maxUpdateTime} MINUTE'")
            ->whereJsonContains('services', $serviceSlug)
            // Règle 2 : pas de location chevauchante pour ce chauffeur
            ->whereDoesntHave('orders', function ($q) use ($startDate, $endDate, $cancelledStatuses) {
                $q->where('is_location', true)
                  ->whereNotIn('status', $cancelledStatuses)
                  ->whereHas('orderItems', function ($sq) use ($startDate, $endDate) {
                      $sq->where('location_start_date', '<=', $endDate)
                         ->where('location_end_date', '>=', $startDate);
                  });
            });

        if ($isUrgent) {
            // Règle 5 : prioriser les chauffeurs sans location active aujourd'hui
            $today = now()->toDateString();
            $query->selectRaw(
                '(SELECT COUNT(*) FROM orders o
                   JOIN order_items oi ON oi.order_id = o.id
                  WHERE o.driver_id = drivers.id
                    AND o.is_location = true
                    AND o.status NOT IN (?)
                    AND oi.location_start_date <= ?
                    AND oi.location_end_date >= ?) as active_rental_today',
                [implode(',', $cancelledStatuses), $today, $today]
            )
            ->selectRaw(
                '(SELECT COUNT(*) FROM orders o2
                  WHERE o2.driver_id = drivers.id
                    AND o2.delivery_type_code = ?
                    AND o2.is_completed = false
                    AND o2.is_draft = false) as week_order_count',
                ['en-semaine']
            )
            ->orderByRaw('active_rental_today ASC')
            ->orderByRaw('week_order_count ASC')
            ->orderByRaw('last_location <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography', [$longitude, $latitude]);
        } else {
            $query->orderByRaw(
                'last_location <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography',
                [$longitude, $latitude]
            );
        }

        return $query->limit($limit)->get();
    }
}
