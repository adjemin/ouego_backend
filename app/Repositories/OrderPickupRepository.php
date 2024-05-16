<?php

namespace App\Repositories;

use App\Models\OrderPickup;
use App\Repositories\BaseRepository;

class OrderPickupRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'order_id',
        'location_name',
        'location_latitude',
        'location_longitude'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return OrderPickup::class;
    }
}
