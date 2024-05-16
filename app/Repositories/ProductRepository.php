<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\BaseRepository;

class ProductRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'name',
        'slug',
        'price',
        'per',
        'pricing_title',
        'description',
        'color',
        'icon',
        'product_types',
        'currency_code',
        'tonne_options'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Product::class;
    }
}
