<?php

namespace App\Payments\Data;

/** Webhook dont la signature a été vérifiée : il ne sert que de signal pour relire le statut. */
final class GatewayWebhookEvent
{
    const KIND_PAYMENT = 'payment';
    const KIND_TRANSFER = 'transfer';

    public function __construct(
        public readonly string $event,
        public readonly ?string $providerPaymentId,
        public readonly ?string $reference,
        public readonly string $kind = 'payment',
    ) {
    }
}
