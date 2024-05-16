<?php

namespace App\Repositories;

use App\Models\Driver;
use App\Repositories\BaseRepository;

class DriverRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'first_name',
        'last_name',
        'name',
        'dialing_code',
        'phone_number',
        'phone',
        'photo_url',
        'is_active',
        'current_balance',
        'old_balance',
        'last_location_latitude',
        'last_location_longitude',
        'services',
        'driver_license_docs'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Driver::class;
    }
}
