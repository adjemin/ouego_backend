<?php

namespace App\Payments\Data;

/** Statut d'un paiement tel que l'opérateur le rapporte, ramené à trois valeurs neutres. */
final class GatewayPaymentStatus
{
    const PENDING = 'pending';
    const SUCCESSFUL = 'successful';
    const FAILED = 'failed';

    public function __construct(
        public readonly string $status,
        public readonly ?int $amount = null,
        public readonly ?string $payerPhone = null,
        public readonly ?string $payerMethod = null,
        public readonly array $raw = [],
    ) {
    }
}
