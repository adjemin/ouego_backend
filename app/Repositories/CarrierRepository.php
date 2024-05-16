<?php

namespace App\Repositories;

use App\Models\Carrier;
use App\Repositories\BaseRepository;

class CarrierRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'name',
        'phone',
        'location_latitude',
        'location_longitude',
        'is_active',
        'products'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Carrier::class;
    }
}
