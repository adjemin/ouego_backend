<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
        'payment_gateway_designation',
        'provider',
        'provider_payment_id',
        'payment_url',
        'expires_at',
        'last_checked_at',
        'check_attempts'
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
        'payment_gateway_designation' => 'string',
        'provider' => 'string',
        'provider_payment_id' => 'string',
        'expires_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'check_attempts' => 'integer'
    ];

    public static array $rules = [

    ];

    //Static methods

    // Référence unique, transmise à l'opérateur (Jèko : 5 à 100 caractères)
    public static function generateReference(): string
    {
        return 'OUEGO-'.Str::ulid();
    }

    // Un paiement dans un état final ne change plus : les confirmations en double sont ignorées
    public function isFinal(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUCCESSFUL,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ], true);
    }

    public function refund(): HasOne
    {
        return $this->hasOne(PaymentRefund::class);
    }
}
