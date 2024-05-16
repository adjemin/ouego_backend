<?php

namespace App\Repositories;

use App\Models\Payment;
use App\Repositories\BaseRepository;

class PaymentRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'invoice_id',
        'payment_method_code',
        'payment_reference',
        'amount',
        'currency_code',
        'user_id',
        'status',
        'is_waiting',
        'is_completed',
        'payment_gateway_trans_id',
        'payment_gateway_custom',
        'payment_gateway_currency',
        'payment_gateway_amount',
        'payment_gateway_payment_date',
        'payment_gateway_error_message',
        'payment_gateway_payment_method',
        'payment_gateway_buyer_name',
        'payment_gateway_buyer_reference',
        'payment_gateway_trans_status',
        'payment_gateway_designation'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Payment::class;
    }
}
