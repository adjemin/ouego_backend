<?php

namespace Tests\Support;

use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Data\GatewayCheckout;
use App\Payments\Data\GatewayPaymentStatus;
use App\Payments\Data\GatewayRefundResult;
use App\Payments\Data\GatewayWebhookEvent;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\InvalidWebhookSignature;
use Illuminate\Http\Request;

/**
 * Opérateur de paiement en mémoire pour tester la logique métier sans HTTP.
 * Enregistré dans config('payments.providers.fake') par FakePaymentGateway::install().
 */
class FakePaymentGateway implements PaymentGateway
{
    public bool $configured = true;

    public int $maxAmount = 2000000;

    public int $createCalls = 0;

    public int $refundCalls = 0;

    public int $statusCalls = 0;

    public bool $failCreate = false;

    public ?GatewayPaymentStatus $nextStatus = null;

    public ?GatewayRefundResult $nextRefund = null;

    public ?GatewayRefundResult $nextRefundStatus = null;

    public bool $failStatus = false;

    /** Installe le faux opérateur sous le code "fake" et le rend actif. */
    public static function install(bool $activate = true): self
    {
        $fake = new self();
        config(['payments.providers.fake' => ['class' => self::class]]);
        app()->instance(self::class, $fake);
        if ($activate) {
            \App\Models\Setting::updateOrCreate(['name' => 'PAYMENT_ACTIVE_PROVIDER'], ['value' => 'fake']);
            \App\Models\Setting::flushCache();
        }

        return $fake;
    }

    public function willReturnStatus(GatewayPaymentStatus $status): self
    {
        $this->nextStatus = $status;

        return $this;
    }

    public function willFailCreate(): self
    {
        $this->failCreate = true;

        return $this;
    }

    public function willReturnRefund(GatewayRefundResult $result): self
    {
        $this->nextRefund = $result;

        return $this;
    }

    public function code(): string
    {
        return 'fake';
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function supportedMethods(): array
    {
        return [['code' => 'wave', 'label' => 'Wave'], ['code' => 'orange', 'label' => 'Orange Money']];
    }

    public function maxAmount(): int
    {
        return $this->maxAmount;
    }

    public function createPayment(Payment $payment, string $method, string $successUrl, string $errorUrl): GatewayCheckout
    {
        $this->createCalls++;
        if ($this->failCreate) {
            throw new GatewayException('fake: création refusée');
        }

        return new GatewayCheckout('fake-'.$payment->payment_reference, 'https://pay.fake/'.$payment->payment_reference);
    }

    public function fetchPaymentStatus(Payment $payment): GatewayPaymentStatus
    {
        $this->statusCalls++;
        if ($this->failStatus) {
            throw new GatewayException('fake: statut indisponible');
        }

        return $this->nextStatus ?? new GatewayPaymentStatus(GatewayPaymentStatus::PENDING);
    }

    public function parseWebhook(Request $request): GatewayWebhookEvent
    {
        if ($request->header('X-Fake-Signature') !== 'ok') {
            throw new InvalidWebhookSignature('fake: signature invalide');
        }

        return new GatewayWebhookEvent(
            'TRANSACTION_COMPLETED',
            $request->input('provider_payment_id'),
            $request->input('reference')
        );
    }

    public function refund(PaymentRefund $refund, Payment $payment): GatewayRefundResult
    {
        $this->refundCalls++;

        return $this->nextRefund ?? new GatewayRefundResult(PaymentRefund::PROCESSING, 'fake-refund-'.$refund->id);
    }

    public function fetchRefundStatus(PaymentRefund $refund): GatewayRefundResult
    {
        return $this->nextRefundStatus ?? new GatewayRefundResult(PaymentRefund::PROCESSING, $refund->provider_refund_id);
    }
}
