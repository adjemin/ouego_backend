<?php

namespace App\Repositories;

use App\Models\CustomerDevice;
use App\Repositories\BaseRepository;

class CustomerDeviceRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'customer_id',
        'firebase_id'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return CustomerDevice::class;
    }
}
