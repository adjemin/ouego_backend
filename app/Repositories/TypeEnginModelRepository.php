<?php

namespace App\Repositories;

use App\Models\TypeEnginModel;
use App\Repositories\BaseRepository;

class TypeEnginModelRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'title',
        'subtitle',
        'slug',
        'group_tag',
        'type_engin_slug',
        'serie',
        'carrosserie',
        'nombre_essieux',
        'nombre_roues',
        'oil',
        'ability_tonne',
        'ptac_tonne',
        'poids_vide',
        'charge_utile',
        'puissance_fiscale',
        'cylindree'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return TypeEnginModel::class;
    }
}
