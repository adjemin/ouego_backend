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
        'transfer_sequence',
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
        'transfer_sequence' => 'integer',
        'next_attempt_at' => 'datetime',
    ];

    /** Référence du transfert en cours chez l'opérateur (voir la migration transfer_sequence). */
    public function providerReference(): string
    {
        return $this->reference.'-'.($this->transfer_sequence ?? 1);
    }

    /** Retrouve un remboursement depuis la référence d'un de ses transferts. */
    public static function findByProviderReference(?string $providerReference): ?self
    {
        if (! $providerReference || ! preg_match('/^(.+)-\d+$/', $providerReference, $m)) {
            return null;
        }

        return static::where('reference', $m[1])->first();
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
