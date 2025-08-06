<?php

namespace App\Services;

use App\Models\Carrier;
use Illuminate\Support\Facades\DB;
use App\Models\ZoneMapping;
use App\Utilities\GoogleMapsAPIUtils;

class CarrierLocationService
{
    /**
     * Trouve les carriers les plus proches d'une localisation donnée.
     *
     * @param float $latitude Latitude de la localisation
     * @param float $longitude Longitude de la localisation
     * @param int $limit Nombre maximum de carriers à retourner
     * @param float $maxDistance Distance maximum en mètres (optionnel)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findNearestCarriers($latitude, $longitude, $limit = 5, $maxDistance = null)
    {
        // Utilisation de l'index R-Tree de PostgreSQL pour une recherche efficace
        $query = Carrier::select('carriers.*')
            ->selectRaw('ST_Distance(location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance', [$longitude, $latitude])
            ->whereRaw('is_active = true')
            ->orderByRaw('location <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography', [$longitude, $latitude]);

        if ($maxDistance) {
            $query->whereRaw('ST_DWithin(location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$longitude, $latitude, $maxDistance]);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Trouve les carriers les plus proches ayant un produit spécifique.
     *
     * @param float $latitude Latitude de la localisation
     * @param float $longitude Longitude de la localisation
     * @param string $product Produit recherché
     * @param int $limit Nombre maximum de carriers à retourner
     * @param float $maxDistance Distance maximum en mètres (optionnel)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findNearestCarriersWithProduct($latitude, $longitude, $product, $limit = 5, $maxDistance = null)
    {
        $zone = GoogleMapsAPIUtils::trouverZoneParPointPostGIS($longitude, $latitude);

        if(empty($zone)){
            throw new \Exception("Désolé, votre position n'est pas couverte par notre zone de livraison");
        }

        $zoneMapping = ZoneMapping::where('zone_id', $zone->id)->get();
        if(empty($zoneMapping)){
            throw new \Exception("Aucune carrière à proximité trouvée à cette zone");
        }

        $carrierIds = $zoneMapping->pluck('carrier_id')->toArray();
        

        $query = Carrier::select('carriers.*')
            ->whereIn('id', $carrierIds)
            ->selectRaw('ST_Distance(location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance', [$longitude, $latitude])
            ->whereRaw('is_active = true')
            ->whereJsonContains('products', $product)
            ->orderByRaw('location <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography', [$longitude, $latitude]);


        if ($maxDistance) {
            $query->whereRaw('ST_DWithin(location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$longitude, $latitude, $maxDistance]);
        }

        return $query->limit($limit)->get();
    }
}
