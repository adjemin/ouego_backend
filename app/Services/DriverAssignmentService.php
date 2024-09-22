<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Driver;

class DriverAssignmentService
{
    /**
     * Trouve les chauffeurs les plus proches d'une localisation donnée.
     *
     * @param float $latitude Latitude de la localisation de départ
     * @param float $longitude Longitude de la localisation de départ
     * @param int $limit Nombre maximum de chauffeurs à retourner
     * @param float $maxDistance Distance maximum en mètres (optionnel)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findNearestDrivers($latitude, $longitude, $limit = 5, $maxDistance = null)
    {
        /*// Crée un point GEOGRAPHY à partir des coordonnées
        $point = DB::raw("ST_SetSRID(ST_MakePoint($longitude, $latitude), 4326)::geography");

        // Commence la requête
        $query = Driver::select('drivers.*')
            ->addSelect(DB::raw("ST_Distance($point, last_location) as distance"))
            ->where('is_available', true)
            ->orderBy('distance');

        // Ajoute une condition de distance maximale si spécifiée
        if ($maxDistance) {
            $query->whereRaw("ST_DWithin($point, last_location, ?)", [$maxDistance]);
        }

        // Exécute la requête et retourne les résultats
        return $query->limit($limit)->get();*/

        // Utilisation de l'index R-Tree de PostgreSQL pour une recherche efficace
        $query = Driver::select('drivers.*')
        ->selectRaw('ST_Distance(last_location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance', [$longitude, $latitude])
        ->whereRaw('is_available = true')
        ->orderByRaw('last_location <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography', [$longitude, $latitude]);

        if ($maxDistance) {
            $query->whereRaw('ST_DWithin(last_location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$longitude, $latitude, $maxDistance]);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Assigne une course au chauffeur le plus proche disponible.
     *
     * @param float $latitude Latitude du point de départ
     * @param float $longitude Longitude du point de départ
     * @return Driver|null Le chauffeur assigné ou null si aucun n'est disponible
     */
    public function assignNearestDriver($latitude, $longitude)
    {
        $nearestDrivers = $this->findNearestDrivers($latitude, $longitude, 1);

        if ($nearestDrivers->isEmpty()) {
            return null;
        }

        $driver = $nearestDrivers->first();

        // Ici, vous pouvez ajouter la logique pour assigner effectivement la course au chauffeur
        // Par exemple, mettre à jour le statut du chauffeur, créer un enregistrement de course, etc.

        return $driver;
    }
}
