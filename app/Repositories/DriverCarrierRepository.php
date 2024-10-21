<?php

namespace App\Repositories;

use App\Models\DriverCarrier;
use App\Repositories\BaseRepository;

class DriverCarrierRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'driver_id',
        'carrier_id'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return DriverCarrier::class;
    }
}
