<?php

namespace Tests\Unit\Payments;

use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Payments\Data\GatewayPaymentStatus;
use App\Payments\Data\GatewayWebhookEvent;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\InvalidWebhookSignature;
use App\Payments\Gateways\JekoGateway;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Adaptateur Jèko : format des appels (montants en centimes), lecture du statut, signature des webhooks.
 * Doc : https://developer.jeko.africa/docs/payments/checkout
 */
class JekoGatewayTest extends TestCase
{
    private const SECRET = 'whsec_test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.providers.jeko.base_url' => 'https://api.jeko.africa',
            'payments.providers.jeko.api_key' => 'key_test',
            'payments.providers.jeko.api_key_id' => 'key_id_test',
            'payments.providers.jeko.store_id' => 'store-uuid',
            'payments.providers.jeko.webhook_secret' => self::SECRET,
        ]);

        Http::preventStrayRequests();
    }

    private function gateway(): JekoGateway
    {
        return app(JekoGateway::class);
    }

    private function payment(array $attributes = []): Payment
    {
        return Payment::create($attributes + [
            'payment_reference' => 'OUEGO-REF-1',
            'amount' => 237500,
            'provider' => 'jeko',
            'provider_payment_id' => 'pr-uuid',
        ]);
    }

    /** @test */
    public function creates_a_payment_request_with_amount_in_cents()
    {
        Http::fake(['api.jeko.africa/partner_api/payment_requests' => Http::response([
            'id' => 'pr-uuid',
            'status' => 'pending',
            'redirectUrl' => 'https://pay.jeko.africa/pay_request/pr/pr-uuid',
        ])]);

        $checkout = $this->gateway()->createPayment($this->payment(), 'wave', 'https://api/ok', 'https://api/ko');

        $this->assertSame('pr-uuid', $checkout->providerPaymentId);
        $this->assertSame('https://pay.jeko.africa/pay_request/pr/pr-uuid', $checkout->paymentUrl);

        Http::assertSent(function (HttpRequest $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.jeko.africa/partner_api/payment_requests'
                && $request->hasHeader('X-API-KEY', 'key_test')
                && $request->hasHeader('X-API-KEY-ID', 'key_id_test')
                && $request['storeId'] === 'store-uuid'
                && $request['amountCents'] === 23750000
                && $request['currency'] === 'XOF'
                && $request['reference'] === 'OUEGO-REF-1'
                && $request['paymentDetails'] === [
                    'type' => 'redirect',
                    'data' => ['paymentMethod' => 'wave', 'successUrl' => 'https://api/ok', 'errorUrl' => 'https://api/ko'],
                ];
        });
    }

    /** @test */
    public function a_failed_creation_throws_a_gateway_exception()
    {
        Http::fake(['api.jeko.africa/*' => Http::response(['message' => 'invalid'], 422)]);

        $this->expectException(GatewayException::class);

        $this->gateway()->createPayment($this->payment(), 'wave', 'https://api/ok', 'https://api/ko');
    }

    /** @test */
    public function a_creation_without_redirect_url_throws_a_gateway_exception()
    {
        Http::fake(['api.jeko.africa/*' => Http::response(['id' => 'pr-uuid', 'status' => 'pending'])]);

        $this->expectException(GatewayException::class);

        $this->gateway()->createPayment($this->payment(), 'wave', 'https://api/ok', 'https://api/ko');
    }

    public static function statuses(): array
    {
        return [
            'pending' => ['pending', GatewayPaymentStatus::PENDING],
            'success' => ['success', GatewayPaymentStatus::SUCCESSFUL],
            'error' => ['error', GatewayPaymentStatus::FAILED],
        ];
    }

    /**
     * @test
     * @dataProvider statuses
     */
    public function maps_success_error_and_pending_statuses(string $jekoStatus, string $expected)
    {
        Http::fake(['api.jeko.africa/partner_api/payment_requests/pr-uuid' => Http::response([
            'id' => 'pr-uuid',
            'status' => $jekoStatus,
            'paymentMethod' => 'wave',
        ])]);

        $this->assertSame($expected, $this->gateway()->fetchPaymentStatus($this->payment())->status);
    }

    /** @test */
    public function a_successful_status_carries_the_amount_in_francs_and_the_payer()
    {
        Http::fake(['api.jeko.africa/partner_api/payment_requests/pr-uuid' => Http::response([
            'id' => 'pr-uuid',
            'status' => 'success',
            'paymentMethod' => 'wave',
            'transaction' => [
                'id' => 'txn_1',
                'amount' => ['amount' => 23750000, 'currency' => 'XOF'],
                'fees' => ['amount' => 356250, 'currency' => 'XOF'],
                'status' => 'success',
                'counterpartIdentifier' => '+2250701234567',
            ],
        ])]);

        $status = $this->gateway()->fetchPaymentStatus($this->payment());

        $this->assertSame(237500, $status->amount);
        $this->assertSame('+2250701234567', $status->payerPhone);
        $this->assertSame('wave', $status->payerMethod);
    }

    /** @test */
    public function an_unreachable_provider_throws_a_gateway_exception_on_status()
    {
        Http::fake(['api.jeko.africa/*' => Http::response('down', 503)]);

        $this->expectException(GatewayException::class);

        $this->gateway()->fetchPaymentStatus($this->payment());
    }

    /** @test */
    public function a_valid_signature_is_accepted()
    {
        $body = json_encode([
            'id' => 'txn_1',
            'status' => 'success',
            'transactionType' => 'payment',
            'transactionDetails' => ['id' => 'pr-uuid', 'reference' => 'OUEGO-REF-1'],
        ]);

        $event = $this->gateway()->parseWebhook($this->webhookRequest($body, hash_hmac('sha256', $body, self::SECRET)));

        $this->assertSame('TRANSACTION_COMPLETED', $event->event);
        $this->assertSame('pr-uuid', $event->providerPaymentId);
        $this->assertSame('OUEGO-REF-1', $event->reference);
        $this->assertSame(GatewayWebhookEvent::KIND_PAYMENT, $event->kind);
    }

    /** @test */
    public function a_tampered_body_is_rejected()
    {
        $body = json_encode(['transactionDetails' => ['id' => 'pr-uuid']]);
        $signature = hash_hmac('sha256', $body, self::SECRET);

        $this->expectException(InvalidWebhookSignature::class);

        $this->gateway()->parseWebhook($this->webhookRequest(str_replace('pr-uuid', 'pr-other', $body), $signature));
    }

    /** @test */
    public function a_missing_signature_is_rejected()
    {
        $this->expectException(InvalidWebhookSignature::class);

        $this->gateway()->parseWebhook($this->webhookRequest('{}', null));
    }

    /** @test */
    public function a_webhook_is_rejected_when_no_secret_is_configured()
    {
        config(['payments.providers.jeko.webhook_secret' => null]);
        $body = '{}';

        $this->expectException(InvalidWebhookSignature::class);

        $this->gateway()->parseWebhook($this->webhookRequest($body, hash_hmac('sha256', $body, '')));
    }

    /** @test */
    public function is_not_configured_without_keys()
    {
        $this->assertTrue($this->gateway()->isConfigured());

        config(['payments.providers.jeko.api_key' => null]);

        $this->assertFalse($this->gateway()->isConfigured());
    }

    private function refund(array $attributes = []): PaymentRefund
    {
        return PaymentRefund::create($attributes + [
            'payment_id' => $this->payment()->id,
            'provider' => 'jeko',
            'amount' => 5000,
            'reference' => 'RF-OUEGO-REF-1',
            'recipient_phone' => '+2250700000001',
            'recipient_method' => 'orange',
            'status' => PaymentRefund::PENDING,
        ]);
    }

    /** @test */
    public function refund_creates_a_contact_then_a_transfer()
    {
        Http::fake([
            'api.jeko.africa/partner_api/contacts' => Http::response(['id' => 'contact-uuid']),
            'api.jeko.africa/partner_api/transfers' => Http::response(['id' => 'wth_abc', 'status' => 'pending']),
        ]);
        $refund = $this->refund();

        $result = $this->gateway()->refund($refund, $refund->payment);

        $this->assertSame(PaymentRefund::PROCESSING, $result->status);
        $this->assertSame('wth_abc', $result->providerRefundId);
        Http::assertSentInOrder([
            fn (HttpRequest $request) => $request->url() === 'https://api.jeko.africa/partner_api/contacts'
                && $request->hasHeader('X-API-KEY', 'key_test')
                && $request['paymentMethod'] === 'orange_money'
                && $request['identifier'] === ['number' => '+2250700000001']
                && ! empty($request['name']),
            fn (HttpRequest $request) => $request->url() === 'https://api.jeko.africa/partner_api/transfers'
                && $request['storeId'] === 'store-uuid'
                && $request['contactId'] === 'contact-uuid'
                && $request['amountCents'] === 500000
                && $request['currency'] === 'XOF'
                && $request['reference'] === 'RF-OUEGO-REF-1-1'
                && mb_strlen($request['description']) <= 255,
        ]);
    }

    /** @test */
    public function a_local_phone_number_is_sent_in_international_format()
    {
        Http::fake([
            'api.jeko.africa/partner_api/contacts' => Http::response(['id' => 'contact-uuid']),
            'api.jeko.africa/partner_api/transfers' => Http::response(['id' => 'wth_abc', 'status' => 'success']),
        ]);
        $refund = $this->refund(['recipient_phone' => '07 00 00 00 01', 'recipient_method' => 'wave']);

        $result = $this->gateway()->refund($refund, $refund->payment);

        $this->assertSame(PaymentRefund::SUCCESSFUL, $result->status);
        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/contacts')
            && $request['identifier'] === ['number' => '+2250700000001']
            && $request['paymentMethod'] === 'wave');
    }

    /** @test */
    public function a_conflict_on_transfer_is_treated_as_already_sent()
    {
        Http::fake([
            'api.jeko.africa/partner_api/contacts' => Http::response(['id' => 'contact-uuid']),
            'api.jeko.africa/partner_api/transfers' => Http::response(['message' => 'Conflict'], 409),
        ]);
        $refund = $this->refund();

        $result = $this->gateway()->refund($refund, $refund->payment);

        // Pas d'appel documenté pour retrouver un transfert par référence : le webhook rattachera son id
        $this->assertSame(PaymentRefund::PROCESSING, $result->status);
        $this->assertNull($result->providerRefundId);
    }

    /** @test */
    public function a_refused_transfer_is_a_definitive_failure()
    {
        Http::fake([
            'api.jeko.africa/partner_api/contacts' => Http::response(['id' => 'contact-uuid']),
            'api.jeko.africa/partner_api/transfers' => Http::response(['message' => 'Solde insuffisant'], 400),
        ]);
        $refund = $this->refund();

        $result = $this->gateway()->refund($refund, $refund->payment);

        $this->assertSame(PaymentRefund::FAILED, $result->status);
        $this->assertStringContainsString('Solde insuffisant', $result->error);
    }

    /** @test */
    public function a_server_error_on_transfer_is_ambiguous()
    {
        Http::fake([
            'api.jeko.africa/partner_api/contacts' => Http::response(['id' => 'contact-uuid']),
            'api.jeko.africa/partner_api/transfers' => Http::response('oops', 502),
        ]);
        $refund = $this->refund();

        $this->expectException(GatewayException::class);

        $this->gateway()->refund($refund, $refund->payment);
    }

    /** @test */
    public function the_transfer_status_is_read_and_mapped()
    {
        Http::fake(['api.jeko.africa/partner_api/transfers/wth_abc' => Http::sequence()
            ->push(['id' => 'wth_abc', 'status' => 'pending'])
            ->push(['id' => 'wth_abc', 'status' => 'success'])
            ->push(['id' => 'wth_abc', 'status' => 'error'])]);
        $refund = $this->refund(['provider_refund_id' => 'wth_abc', 'status' => PaymentRefund::PROCESSING]);

        $this->assertSame(PaymentRefund::PROCESSING, $this->gateway()->fetchRefundStatus($refund)->status);
        $this->assertSame(PaymentRefund::SUCCESSFUL, $this->gateway()->fetchRefundStatus($refund)->status);
        $this->assertSame(PaymentRefund::FAILED, $this->gateway()->fetchRefundStatus($refund)->status);
    }

    private function webhookRequest(string $body, ?string $signature): Request
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_JEKO_EVENT' => 'TRANSACTION_COMPLETED'];
        if ($signature !== null) {
            $server['HTTP_JEKO_SIGNATURE'] = $signature;
        }

        return Request::create('/api/v1/payments/webhook/jeko', 'POST', [], [], [], $server, $body);
    }
}
