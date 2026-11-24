<?php

namespace App\Payments\Data;

use Carbon\CarbonInterface;

/** Paiement créé chez l'opérateur : son identifiant et la page où le client paie. */
final class GatewayCheckout
{
    public function __construct(
        public readonly string $providerPaymentId,
        public readonly string $paymentUrl,
        public readonly ?CarbonInterface $expiresAt = null,
    ) {
    }
}
