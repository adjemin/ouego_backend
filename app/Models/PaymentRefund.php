<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Remboursement d'un paiement en ligne, envoyé par l'opérateur d'origine du paiement.
 */
class PaymentRefund extends Model
{
    public $table = 'payment_refunds';

    const PENDING = 'PENDING';
    const PROCESSING = 'PROCESSING';
    const SUCCESSFUL = 'SUCCESSFUL';
    const FAILED = 'FAILED';

    public $fillable = [
        'payment_id',
        'order_id',
        'provider',
        'amount',
        'currency_code',
        'reference',
        'provider_refund_id',
        'recipient_phone',
        'recipient_method',
        'status',
        'attempts',
        'next_attempt_at',
        'last_error',
    ];

    protected $casts = [
        'payment_id' => 'integer',
        'order_id' => 'integer',
        'amount' => 'integer',
        'attempts' => 'integer',
        'next_attempt_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
