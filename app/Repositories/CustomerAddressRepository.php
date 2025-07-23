<?php

namespace App\Repositories;

use App\Models\CustomerAddress;
use App\Repositories\BaseRepository;

class CustomerAddressRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'address_name',
        'latitude',
        'longitude',
        'details'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return CustomerAddress::class;
    }
}
