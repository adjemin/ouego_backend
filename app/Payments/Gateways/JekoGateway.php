<?php

namespace App\Payments\Gateways;

use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Data\GatewayCheckout;
use App\Payments\Data\GatewayPaymentStatus;
use App\Payments\Data\GatewayRefundResult;
use App\Payments\Data\GatewayWebhookEvent;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\InvalidWebhookSignature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Opérateur Jèko (https://developer.jeko.africa).
 *
 * Les montants envoyés et reçus sont en centimes (1 XOF = 100). La doc ne précise pas l'unité de
 * transaction.amount.amount dans les réponses ; l'exemple (10000 pour amountCents 10000) indique des centimes.
 */
class JekoGateway implements PaymentGateway
{
    private const STATUS_MAP = [
        'pending' => GatewayPaymentStatus::PENDING,
        'success' => GatewayPaymentStatus::SUCCESSFUL,
        'error' => GatewayPaymentStatus::FAILED,
    ];

    public function code(): string
    {
        return 'jeko';
    }

    private function config(string $key): mixed
    {
        return config("payments.providers.jeko.{$key}");
    }

    public function isConfigured(): bool
    {
        foreach (['base_url', 'api_key', 'api_key_id', 'store_id', 'webhook_secret'] as $key) {
            if (empty($this->config($key))) {
                return false;
            }
        }

        return true;
    }

    public function supportedMethods(): array
    {
        return [
            ['code' => 'wave', 'label' => 'Wave'],
            ['code' => 'orange', 'label' => 'Orange Money'],
            ['code' => 'mtn', 'label' => 'MTN MoMo'],
            ['code' => 'moov', 'label' => 'Moov Money'],
            ['code' => 'djamo', 'label' => 'Djamo'],
        ];
    }

    public function maxAmount(): int
    {
        return (int) $this->config('max_amount');
    }

    public function createPayment(Payment $payment, string $method, string $successUrl, string $errorUrl): GatewayCheckout
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post('/partner_api/payment_requests', [
            'storeId' => $this->config('store_id'),
            'amountCents' => (int) round($payment->amount) * 100,
            'currency' => 'XOF',
            'reference' => $payment->payment_reference,
            'paymentDetails' => [
                'type' => 'redirect',
                'data' => [
                    'paymentMethod' => $method,
                    'successUrl' => $successUrl,
                    'errorUrl' => $errorUrl,
                ],
            ],
        ]), 'création du paiement');

        $id = $response->json('id');
        $url = $response->json('redirectUrl');
        if (empty($id) || empty($url)) {
            throw new GatewayException('Jèko : réponse de création sans id ou redirectUrl : '.$response->body());
        }

        return new GatewayCheckout($id, $url);
    }

    public function fetchPaymentStatus(Payment $payment): GatewayPaymentStatus
    {
        if (empty($payment->provider_payment_id)) {
            throw new GatewayException("Jèko : paiement {$payment->id} sans provider_payment_id");
        }

        $response = $this->send(
            fn (PendingRequest $http) => $http->get('/partner_api/payment_requests/'.rawurlencode($payment->provider_payment_id)),
            'lecture du statut'
        );

        $amountCents = $response->json('transaction.amount.amount');

        return new GatewayPaymentStatus(
            self::STATUS_MAP[$response->json('status')] ?? GatewayPaymentStatus::PENDING,
            $amountCents === null ? null : intdiv((int) $amountCents, 100),
            $response->json('transaction.counterpartIdentifier'),
            $response->json('paymentMethod'),
            $response->json() ?? [],
        );
    }

    public function parseWebhook(Request $request): GatewayWebhookEvent
    {
        $secret = (string) $this->config('webhook_secret');
        $signature = (string) $request->header('Jeko-Signature', '');

        if ($secret === '' || $signature === ''
            || ! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), strtolower($signature))) {
            throw new InvalidWebhookSignature('Jèko : signature de webhook invalide');
        }

        $body = json_decode($request->getContent(), true) ?: [];

        return new GatewayWebhookEvent(
            (string) $request->header('Jeko-Event', ''),
            data_get($body, 'transactionDetails.id'),
            data_get($body, 'transactionDetails.reference'),
            data_get($body, 'transactionType') === 'transfer' ? GatewayWebhookEvent::KIND_TRANSFER : GatewayWebhookEvent::KIND_PAYMENT,
        );
    }

    public function refund(PaymentRefund $refund, Payment $payment): GatewayRefundResult
    {
        throw new GatewayException('Jèko : remboursement non implémenté');
    }

    public function fetchRefundStatus(PaymentRefund $refund): GatewayRefundResult
    {
        throw new GatewayException('Jèko : remboursement non implémenté');
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) $this->config('base_url'), '/'))
            ->withHeaders([
                'X-API-KEY' => (string) $this->config('api_key'),
                'X-API-KEY-ID' => (string) $this->config('api_key_id'),
            ])
            ->acceptJson()
            ->asJson()
            ->timeout((int) $this->config('timeout'));
    }

    /** Envoie la requête et convertit toute erreur réseau ou HTTP en GatewayException. */
    private function send(callable $call, string $operation): Response
    {
        try {
            $response = $call($this->http());
        } catch (ConnectionException $e) {
            throw new GatewayException("Jèko : {$operation} impossible : ".$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new GatewayException("Jèko : {$operation} refusée (HTTP {$response->status()}) : ".$response->body());
        }

        return $response;
    }
}
