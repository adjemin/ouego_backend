<?php

namespace App\Repositories;

use App\Models\CustomerProfile;
use App\Repositories\BaseRepository;

class CustomerProfileRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'name',
        'description'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return CustomerProfile::class;
    }
}
