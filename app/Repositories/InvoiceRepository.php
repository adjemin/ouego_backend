<?php

namespace App\Repositories;

use App\Models\Invoice;
use App\Repositories\BaseRepository;

class InvoiceRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'order_id',
        'customer_id',
        'reference',
        'subtotal',
        'tax',
        'fees_delivery',
        'total',
        'status',
        'is_paid_by_customer',
        'currency_code',
        'driver_due',
        'service_due',
        'discount',
        'coupon'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Invoice::class;
    }
}
