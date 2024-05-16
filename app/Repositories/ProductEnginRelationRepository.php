<?php

namespace App\Repositories;

use App\Models\ProductEnginRelation;
use App\Repositories\BaseRepository;

class ProductEnginRelationRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'product_id',
        'type_engin_id'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return ProductEnginRelation::class;
    }
}
