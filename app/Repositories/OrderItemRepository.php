<?php

namespace App\Repositories;

use App\Models\OrderItem;
use App\Repositories\BaseRepository;

class OrderItemRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'order_id',
        'service_slug',
        'meta_data',
        'quantity',
        'quantity_unity',
        'unit_price',
        'total_amount',
        'currency'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return OrderItem::class;
    }
}
