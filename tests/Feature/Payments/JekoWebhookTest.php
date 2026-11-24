<?php

namespace Tests\Feature\Payments;

use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Transaction;
use App\Payments\Data\GatewayPaymentStatus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

/**
 * Webhook Jèko : signature obligatoire, et le statut appliqué est toujours relu chez Jèko,
 * jamais pris dans le corps du webhook.
 */
class JekoWebhookTest extends TestCase
{
    private const SECRET = 'whsec_test';

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        Http::preventStrayRequests();

        config([
            'payments.providers.jeko.api_key' => 'key_test',
            'payments.providers.jeko.api_key_id' => 'key_id_test',
            'payments.providers.jeko.store_id' => 'store-uuid',
            'payments.providers.jeko.webhook_secret' => self::SECRET,
        ]);
        Setting::set('PAYMENT_ACTIVE_PROVIDER', 'jeko');

        $this->driver = Driver::factory()->create(['current_balance' => 1000, 'old_balance' => 1000]);
    }

    /** Recharge de 5000 F en attente chez Jèko (payment request pr-uuid). */
    private function pendingDeposit(string $provider = 'jeko', string $providerPaymentId = 'pr-uuid'): Payment
    {
        $transaction = Transaction::create([
            'user_id' => $this->driver->id, 'status' => Transaction::CREATED, 'type' => Transaction::TYPE_DEPOSIT,
            'user_source' => 'drivers', 'currency_code' => 'XOF', 'amount' => 5000, 'is_in' => true,
        ]);
        $invoice = Invoice::create([
            'order_id' => $transaction->id, 'customer_id' => $this->driver->id, 'order_source' => 'transactions',
            'reference' => 'WALLET-'.$transaction->id, 'subtotal' => 5000, 'total' => 5000,
            'status' => Invoice::UNPAID, 'currency_code' => 'XOF',
        ]);

        return Payment::create([
            'invoice_id' => $invoice->id, 'user_id' => $this->driver->id, 'payment_method_code' => 'wave',
            'payment_reference' => Payment::generateReference(), 'amount' => 5000, 'currency_code' => 'XOF',
            'status' => Payment::STATUS_PENDING, 'is_waiting' => true, 'is_completed' => false,
            'provider' => $provider, 'provider_payment_id' => $providerPaymentId,
            'payment_url' => 'https://pay.jeko.africa/x', 'expires_at' => now()->addMinutes(30),
        ]);
    }

    private function jekoReports(string $status, int $amountCents = 500000): void
    {
        Http::fake(['api.jeko.africa/partner_api/payment_requests/pr-uuid' => Http::response([
            'id' => 'pr-uuid',
            'status' => $status,
            'paymentMethod' => 'wave',
            'transaction' => $status === 'success'
                ? ['amount' => ['amount' => $amountCents, 'currency' => 'XOF'], 'counterpartIdentifier' => '+2250700000000']
                : null,
        ])]);
    }

    private function webhook(Payment $payment, ?string $signature = 'valid', string $status = 'success')
    {
        $body = json_encode([
            'id' => 'txn_1',
            'status' => $status,
            'transactionType' => 'payment',
            'transactionDetails' => ['id' => $payment->provider_payment_id, 'reference' => $payment->payment_reference],
        ]);
        $headers = ['Jeko-Event' => 'TRANSACTION_COMPLETED', 'Content-Type' => 'application/json'];
        if ($signature !== null) {
            $headers['Jeko-Signature'] = $signature === 'valid' ? hash_hmac('sha256', $body, self::SECRET) : $signature;
        }

        return $this->call('POST', '/api/v1/payments/webhook/jeko', [], [], [], $this->transformHeadersToServerVars($headers), $body);
    }

    /** @test */
    public function a_signed_webhook_confirms_the_payment()
    {
        $payment = $this->pendingDeposit();
        $this->jekoReports('success');

        $this->webhook($payment)->assertOk()->assertJson(['status' => 'OK']);

        $this->assertSame(Payment::STATUS_SUCCESSFUL, $payment->fresh()->status);
        $this->assertEquals(6000, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function an_unsigned_webhook_is_rejected_with_401_and_changes_nothing()
    {
        $payment = $this->pendingDeposit();
        $this->jekoReports('success');

        $this->webhook($payment, signature: null)->assertStatus(401);
        $this->webhook($payment, signature: 'deadbeef')->assertStatus(401);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertEquals(1000, $this->driver->fresh()->current_balance);
        Http::assertNothingSent();
    }

    /** @test */
    public function a_webhook_for_an_unknown_payment_returns_200()
    {
        $payment = $this->pendingDeposit();
        $payment->provider_payment_id = 'pr-unknown';
        $payment->payment_reference = 'OUEGO-UNKNOWN';

        $this->webhook($payment)->assertOk();

        Http::assertNothingSent();
    }

    /** @test */
    public function a_webhook_for_an_unknown_provider_returns_404()
    {
        $this->postJson('/api/v1/payments/webhook/inconnu', [])->assertStatus(404);
    }

    /** @test */
    public function a_duplicated_webhook_credits_once()
    {
        $payment = $this->pendingDeposit();
        $this->jekoReports('success');

        $this->webhook($payment)->assertOk();
        $this->webhook($payment)->assertOk();

        $this->assertEquals(6000, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function the_webhook_trusts_the_provider_status_not_the_body()
    {
        $payment = $this->pendingDeposit();
        $this->jekoReports('pending');

        $this->webhook($payment, status: 'success')->assertOk();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame(Invoice::UNPAID, Invoice::find($payment->invoice_id)->status);
    }

    /** @test */
    public function an_unreachable_provider_still_answers_200_and_changes_nothing()
    {
        $payment = $this->pendingDeposit();
        Http::fake(['api.jeko.africa/*' => Http::response('down', 503)]);

        $this->webhook($payment)->assertOk();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    /** @test */
    public function a_payment_from_a_deactivated_provider_is_still_confirmed()
    {
        $fake = FakePaymentGateway::install(activate: false);
        $payment = $this->pendingDeposit('fake', 'fake-1');
        $fake->willReturnStatus(new GatewayPaymentStatus(GatewayPaymentStatus::SUCCESSFUL, 5000));

        // Jèko est l'opérateur actif ; le paiement appartient à « fake »
        $this->withHeaders(['X-Fake-Signature' => 'ok'])
            ->postJson('/api/v1/payments/webhook/fake', ['provider_payment_id' => 'fake-1'])
            ->assertOk();

        $this->assertSame(Payment::STATUS_SUCCESSFUL, $payment->fresh()->status);
    }

    /** @test */
    public function a_payment_is_only_matched_within_its_own_provider()
    {
        $fake = FakePaymentGateway::install(activate: false);
        $payment = $this->pendingDeposit('jeko', 'fake-1');
        $fake->willReturnStatus(new GatewayPaymentStatus(GatewayPaymentStatus::SUCCESSFUL, 5000));

        $this->withHeaders(['X-Fake-Signature' => 'ok'])
            ->postJson('/api/v1/payments/webhook/fake', ['provider_payment_id' => 'fake-1'])
            ->assertOk();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }
}
