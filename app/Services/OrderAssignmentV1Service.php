<?php 

namespace App\Services;

use App\Models\Carrier;
use App\Models\Driver;
use App\Utilities\GoogleMapsAPIUtils;
use App\Models\Order;
use App\Models\DriverCarrier;

class OrderAssignmentV1Service
{

    // Ville de référence : Abidjan
    private float $villeLat = 5.3252258;
    private float $villeLng = -4.019603;
    private float $rayonMaxKm = 50;

    public function expressOrderAssignment(float $latitudeLivraison, float $longitudeLivraison, float $rayonKm = 5): array
    {
        // Étape 1 : Récupérer les carrières à proximité de la livraison
        $carrieres = Carrier::all()->filter(function ($carrier) use ($latitudeLivraison, $longitudeLivraison, $rayonKm) {
            $distance = GoogleMapsAPIUtils::distanceHaversine($carrier->location_latitude, $carrier->location_longitude, $latitudeLivraison, $longitudeLivraison);
            return $distance <= $rayonKm;
        });

        $chauffeurAffectations = [];

        // Étape 2 : Récupérer les chauffeurs à proximité de chaque carrière
        foreach ($carrieres as $carriere) {
            $driverIds = DriverCarrier::where('carrier_id', $carriere->id)->pluck("driver_id")->unique();

            $chauffeursProches = Driver::whereIn('id', $driverIds)->get()->map(function ($chauffeur) use ($carriere) {
                $distance = GoogleMapsAPIUtils::distanceHaversine(
                    $carriere->location_latitude,
                    $carriere->location_longitude,
                    $chauffeur->last_location_latitude,
                    $chauffeur->last_location_longitude
                );

                return [
                    'driver_id' => $chauffeur->id,
                    'distance' => ceil($distance),
                    'carrier_id' => $carriere->id,
                ];
            })->filter(fn($item) => $item['distance'] <= 10)
            ->sortBy('distance')
            ->take(10)
            ->values();

            // Étape 3 : Normalisation (conserver carrière la plus proche)
            foreach ($chauffeursProches as $chauffeur) {
                $chauffeurId = $chauffeur['driver_id'];
                if (!isset($chauffeurAffectations[$chauffeurId]) || $chauffeur['distance'] < $chauffeurAffectations[$chauffeurId]['distance']) {
                    $chauffeurAffectations[$chauffeurId] = $chauffeur;
                }
            }
        }

        $chauffeursProches = array_values($chauffeurAffectations);

        if (empty($chauffeursProches)) return [];

        // Étape 4 : Préparation pour calcul des pondérations
        $minDistanceChauffeurCarriere = collect($chauffeursProches)->min('distance');

        $carrieresIds = collect($chauffeursProches)->pluck('carrier_id')->unique();
        $carrieres = Carrier::whereIn('id', $carrieresIds)->get();

        $distancesCarriereLivraison = $carrieres->mapWithKeys(function ($carriere) use ($latitudeLivraison, $longitudeLivraison) {
            $distance = GoogleMapsAPIUtils::distanceHaversine(
                $carriere->location_latitude,
                $carriere->location_longitude,
                $latitudeLivraison,
                $longitudeLivraison
            );
            return [$carriere->id => ceil($distance)];
        });

        $minDistanceCarriereLivraison = $distancesCarriereLivraison->min();

        $carriereChauffeursCount = collect([]);
        foreach ($carrieresIds as $id) {
            $carriereChauffeursCount[$id] = DriverCarrier::where('carrier_id', $id)->distinct('driver_id')->count();
        }

        $minChauffeursCarriere = $carriereChauffeursCount->min();

        $chauffeursIds = collect($chauffeursProches)->pluck('driver_id');
        $chauffeurs = Driver::whereIn('id', $chauffeursIds)->get()->keyBy('id');
        $maxJetons = $chauffeurs->max('current_balance');

        // Étape 5 : Calcul des pondérations
        $ponderations = [];

        foreach ($chauffeursProches as $item) {
            $chauffeur = $chauffeurs[$item['driver_id']];
            $distanceChauffeurCarriere = $item['distance'];
            $distanceCarriereLivraison = $distancesCarriereLivraison[$item['carrier_id']];
            $nbChauffeursCarriere = $carriereChauffeursCount[$item['carrier_id']] ?? 1;

            $score = [];

            $score['proximity_driver_carrier'] = number_format(($minDistanceChauffeurCarriere / $distanceChauffeurCarriere) * 100, 2);
            $score['jetons'] = number_format(($chauffeur->current_balance / $maxJetons) * 100, 2);
            $score['proximity_carrier_delivery'] = number_format(($minDistanceCarriereLivraison / $distanceCarriereLivraison) * 100, 2);
            $score['note'] = number_format(($chauffeur->note / 5) * 100, 2);
            $score['concentration'] = number_format(($minChauffeursCarriere / $nbChauffeursCarriere) * 100, 2);

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
                'carrier_id' => $item['carrier_id'],
                'distance' => $item['distance'],
                'score_total' => $scoreTotal,
                'details' => $score,
            ];
        }

        return collect($ponderations)->sortByDesc('score_total')->values()->all();
    }


    public function ondayOrderAssignment(float $latLivraison, float $lngLivraison, string $typeGravier): array
    {
        // Étape 1 : Récupérer les chauffeurs dans un rayon de 50 km autour du centre-ville
        $chauffeurs = Driver::all()->map(function ($chauffeur) {
            // Vérifier si le chauffeur est actif
            $chauffeur->orders =  Order::where('driver_id', $chauffeur->id)
                ->where('is_draft', false)
                ->where('is_completed', false)
                ->count();


            $chauffeur->distance = ceil(GoogleMapsAPIUtils::distanceHaversine($this->villeLat, $this->villeLng, $chauffeur->last_location_latitude, $chauffeur->last_location_longitude));

    
            return $chauffeur;

        })->filter(fn($item) => $item->distance <= $this->rayonMaxKm);


        if ($chauffeurs->isEmpty()) return [];

        // Étape 2 : Identifier les carrières proches du lieu de livraison (top 8)
        $carriers = Carrier::where('products',"LIKE", "%".$typeGravier."%")->get();

        $carrieresProches = $carriers->map(function ($carrier) use ($latLivraison, $lngLivraison) {
            $carrier->distance = ceil(GoogleMapsAPIUtils::distanceHaversine($carrier->location_latitude, $carrier->location_longitude, $latLivraison, $lngLivraison));
            return $carrier;
        })->sortBy('distance')->take(8)->values();

        if ($carrieresProches->isEmpty()) return [];

        // Étape 3 : Choisir une carrière aléatoire dans le top 8
        $carriereChoisie = $carrieresProches->random();

        // Étape 4 : Normalisation des scores
        $maxCourses = $chauffeurs->max('orders') ?: 1;

        $minDistanceLivraison = $chauffeurs->min(fn($c) => ceil(GoogleMapsAPIUtils::distanceHaversine($c->last_location_latitude, $c->last_location_longitude, $latLivraison, $lngLivraison))) ?: 1;
        $minDistanceCarriere = $chauffeurs->min(fn($c) => ceil(GoogleMapsAPIUtils::distanceHaversine($c->last_location_latitude, $c->last_location_longitude, $carriereChoisie->location_latitude, $carriereChoisie->location_longitude))) ?: 1;
        $maxJetons = $chauffeurs->max('current_balance') ?: 1;

        // Étape 5 : Calcul du score pondéré pour chaque chauffeur
        $classement = $chauffeurs->map(function ($chauffeur) use (
            $latLivraison,
            $lngLivraison,
            $carriereChoisie,
            $maxCourses,
            $minDistanceLivraison,
            $minDistanceCarriere,
            $maxJetons,
        ) {
            $distanceLivraison = ceil(GoogleMapsAPIUtils::distanceHaversine($chauffeur->last_location_latitude, $chauffeur->last_location_longitude, $latLivraison, $lngLivraison));
            $distanceCarriere = ceil(GoogleMapsAPIUtils::distanceHaversine($chauffeur->last_location_latitude, $chauffeur->last_location_longitude, $carriereChoisie->location_latitude, $carriereChoisie->location_longitude));

            return [
                'driver_id' => $chauffeur->id,
                'score_total' =>number_format(
                    (1 - $chauffeur->orders / $maxCourses) * 100 * 0.40 +
                    ($minDistanceLivraison / $distanceLivraison) * 100 * 0.35 +
                    ($minDistanceCarriere / $distanceCarriere) * 100 * 0.15 +
                    ((float)$chauffeur->note / 5) * 100 * 0.05 +
                    ($chauffeur->current_balance / $maxJetons) * 100 * 0.05, 3),
                'details' => [
                    'courses' => $chauffeur->orders,
                    'distance_livraison' => $distanceLivraison,
                    'distance_carriere' => $distanceCarriere,
                    'note' => (float)$chauffeur->note,
                    'jetons' => $chauffeur->current_balance,
                ]
            ];
        })->sortByDesc('score_total')->values()->take(10);

        return $classement->toArray();
    }
}