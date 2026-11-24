<?php

namespace App\Payments\Data;

/** Résultat d'un remboursement : $status vaut PaymentRefund::PROCESSING, SUCCESSFUL ou FAILED. */
final class GatewayRefundResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $providerRefundId = null,
        public readonly ?string $error = null,
    ) {
    }
}
