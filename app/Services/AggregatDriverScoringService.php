<?php

namespace App\Services;

use App\Models\Carrier;
use App\Models\RoutePoint;
use App\Utilities\GoogleMapsAPIUtils;
use Illuminate\Support\Collection;

/**
 * Classe les chauffeurs candidats pour une commande d'agrégats (sable, gravier, ciment).
 *
 * Score sur 100 :
 *  - 35 % proximité chauffeur ↔ carrière
 *  - 25 % jetons (solde du chauffeur)
 *  - 25 % proximité chauffeur ↔ point de livraison
 *  - 15 % note du chauffeur
 */
class AggregatDriverScoringService
{
    const WEIGHT_PROXIMITY_CARRIER = 0.35;
    const WEIGHT_JETONS = 0.25;
    const WEIGHT_PROXIMITY_DELIVERY = 0.25;
    const WEIGHT_NOTE = 0.15;

    // En dessous de cette distance (précision GPS), deux chauffeurs sont considérés aussi proches
    const MIN_DISTANCE_METERS = 100;

    /**
     * @param Collection $drivers Chauffeurs candidats, avec l'attribut `distance` (mètres jusqu'à la carrière)
     * @param RoutePoint|null $delivery Point de livraison (critère neutre s'il est absent)
     * @return array Les $limit meilleurs chauffeurs, du meilleur score au moins bon
     */
    public function rank(Collection $drivers, Carrier $carrier, ?RoutePoint $delivery, int $limit): array
    {
        if ($drivers->isEmpty()) {
            return [];
        }

        $deliveryDistances = $drivers->mapWithKeys(fn ($driver) => [
            $driver->id => $delivery ? $this->floorDistance(GoogleMapsAPIUtils::distanceHaversine(
                $delivery->latitude,
                $delivery->longitude,
                $driver->last_location_latitude,
                $driver->last_location_longitude
            ) * 1000) : null,
        ]);

        $minCarrierDistance = $drivers->min(fn ($driver) => $this->floorDistance($driver->distance));
        $minDeliveryDistance = $deliveryDistances->filter()->min();
        $maxJetons = max(1, $drivers->max(fn ($driver) => floatval($driver->current_balance)));

        return $drivers
            ->map(function ($driver) use ($carrier, $deliveryDistances, $minCarrierDistance, $minDeliveryDistance, $maxJetons) {
                $deliveryDistance = $deliveryDistances[$driver->id];

                $score = [
                    'proximity_driver_carrier' => round($minCarrierDistance / $this->floorDistance($driver->distance) * 100, 2),
                    'jetons' => round(max(0, floatval($driver->current_balance)) / $maxJetons * 100, 2),
                    'proximity_driver_delivery' => $deliveryDistance === null ? 100.0 : round($minDeliveryDistance / $deliveryDistance * 100, 2),
                    'note' => round(floatval($driver->rate) / 5 * 100, 2),
                ];

                $scoreTotal = round(
                    $score['proximity_driver_carrier'] * self::WEIGHT_PROXIMITY_CARRIER +
                    $score['jetons'] * self::WEIGHT_JETONS +
                    $score['proximity_driver_delivery'] * self::WEIGHT_PROXIMITY_DELIVERY +
                    $score['note'] * self::WEIGHT_NOTE,
                    3
                );

                return [
                    'driver_id' => $driver->id,
                    'carrier_id' => $carrier->id,
                    'distance' => $driver->distance,
                    'score_total' => $scoreTotal,
                    'details' => $score,
                ];
            })
            ->sortByDesc('score_total')
            ->values()
            ->take($limit)
            ->all();
    }

    private function floorDistance($meters): float
    {
        return max(self::MIN_DISTANCE_METERS, floatval($meters));
    }
}
