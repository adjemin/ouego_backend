<?php

namespace App\Repositories;

use App\Models\Order;
use App\Repositories\BaseRepository;

class OrderRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'customer_id',
        'driver_id',
        'services',
        'status',
        'comment',
        'order_date',
        'is_started',
        'is_running',
        'is_waiting',
        'is_completed',
        'completion_time',
        'start_time',
        'acceptation_time',
        'expected_arrival_at',
        'rating_id',
        'rating',
        'rating_note',
        'order_price',
        'currency_code',
        'payment_method_code',
        'delivery_type_code',
        'is_location',
        'is_product',
        'is_ride'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Order::class;
    }
}
