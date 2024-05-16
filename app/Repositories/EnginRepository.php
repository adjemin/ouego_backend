<?php

namespace App\Repositories;

use App\Models\Engin;
use App\Repositories\BaseRepository;

class EnginRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'immatriculation',
        'numero_carte_grise',
        'brand',
        'serie',
        'type_engin',
        'carrosserie',
        'color',
        'nombre_essieux',
        'nombre_roues',
        'oil',
        'usages',
        'ability_tonne',
        'ptac_tonne',
        'poids_vide',
        'charge_utile',
        'puissance_fiscale',
        'cylindree',
        'date_mise_en_production',
        'date_edition',
        'nom_proprietaire'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Engin::class;
    }
}
