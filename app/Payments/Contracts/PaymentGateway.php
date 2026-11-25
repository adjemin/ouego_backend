<?php

namespace App\Payments\Contracts;

use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Payments\Data\GatewayCheckout;
use App\Payments\Data\GatewayPaymentStatus;
use App\Payments\Data\GatewayRefundResult;
use App\Payments\Data\GatewayWebhookEvent;
use Illuminate\Http\Request;

/**
 * Ce qu'un opérateur de paiement (Jèko…) doit savoir faire. Aucune règle métier ici :
 * facture, recherche de chauffeur, portefeuille et remboursements sont dans PaymentService.
 */
interface PaymentGateway
{
    public function code(): string;

    public function isConfigured(): bool;

    /** @return list<array{code:string,label:string}> */
    public function supportedMethods(): array;

    /** Montant maximal d'une transaction, en XOF. */
    public function maxAmount(): int;

    /** @throws \App\Payments\Exceptions\GatewayException */
    public function createPayment(Payment $payment, string $method, string $successUrl, string $errorUrl): GatewayCheckout;

    /** @throws \App\Payments\Exceptions\GatewayException */
    public function fetchPaymentStatus(Payment $payment): GatewayPaymentStatus;

    /** @throws \App\Payments\Exceptions\InvalidWebhookSignature */
    public function parseWebhook(Request $request): GatewayWebhookEvent;

    /** @throws \App\Payments\Exceptions\GatewayException */
    public function refund(PaymentRefund $refund, Payment $payment): GatewayRefundResult;

    /** @throws \App\Payments\Exceptions\GatewayException */
    public function fetchRefundStatus(PaymentRefund $refund): GatewayRefundResult;
}
