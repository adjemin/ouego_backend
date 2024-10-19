<?php

namespace App\Repositories;

use App\Models\RoutePoint;
use App\Repositories\BaseRepository;

class RoutePointRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'address_name',
        'latitude',
        'longitude',
        'contact_fullname',
        'contact_phone',
        'contact_second_phone',
        'contact_email',
        'parcel_details',
        'type',
        'images',
        'signatures',
        'signatures_at',
        'status',
        'delivery_fees',
        'currency_code',
        'is_waiting',
        'is_completed',
        'is_successful',
        'has_cash_management',
        'has_cash_deposited',
        'is_driver_paid',
        'completion_time',
        'expected_arrival_at',
        'visit_order',
        'stage',
        'apartment',
        'customer_id',
        'order_id',
        'has_handling'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return RoutePoint::class;
    }
}
