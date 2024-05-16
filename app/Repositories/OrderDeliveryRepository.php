<?php

namespace App\Repositories;

use App\Models\OrderDelivery;
use App\Repositories\BaseRepository;

class OrderDeliveryRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'order_id',
        'location_name',
        'location_latitude',
        'location_longitude',
        'comment'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return OrderDelivery::class;
    }
}
