<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{

    use SoftDeletes;

    public $table = 'payments';

    //status
    const STATUS_INITIATED  = "INITIATED";
    const STATUS_PENDING = "PENDING";
    const STATUS_SUCCESSFUL = "SUCCESSFUL";
    const STATUS_FAILED = "FAILED";
    const STATUS_CANCELLED = "CANCELLED";
    const STATUS_EXPIRED = "EXPIRED";
    const STATUS_REFUSED = "REFUSED";

    public $fillable = [
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

    protected $casts = [
        'invoice_id' => 'integer',
        'payment_method_code' => 'string',
        'payment_reference' => 'string',
        'amount' => 'double',
        'currency_code' => 'string',
        'user_id' => 'integer',
        'status' => 'string',
        'is_waiting' => 'boolean',
        'is_completed' => 'boolean',
        'payment_gateway_trans_id' => 'string',
        'payment_gateway_custom' => 'string',
        'payment_gateway_currency' => 'string',
        'payment_gateway_amount' => 'string',
        'payment_gateway_payment_date' => 'string',
        'payment_gateway_error_message' => 'string',
        'payment_gateway_payment_method' => 'string',
        'payment_gateway_buyer_reference' => 'string',
        'payment_gateway_trans_status' => 'string',
        'payment_gateway_designation' => 'string'
    ];

    public static array $rules = [

    ];

    //Static methods

    //Generate a payment reference
    public static function generateReference(): string
    {
        $reference = substr(md5(time()), 0, 8);
        //check if the reference already exists
        $payment = Payment::where('payment_reference', $reference)->first();
        if ($payment) {
            return self::generateReference();
        }
        return $reference;
    }


}
