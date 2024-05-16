<?php

namespace App\Repositories;

use App\Models\DeliveryType;
use App\Repositories\BaseRepository;

class DeliveryTypeRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'name',
        'icon',
        'slug',
        'is_active'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return DeliveryType::class;
    }
}
