<?php

namespace App\Repositories;

use App\Models\DriverDevice;
use App\Repositories\BaseRepository;

class DriverDeviceRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'driver_id',
        'firebase_id'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return DriverDevice::class;
    }
}
