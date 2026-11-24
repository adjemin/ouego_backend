<?php

namespace Tests\Feature\Payments;

use App\Events\PaymentStatusUpdated;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Transaction;
use App\Payments\Data\GatewayPaymentStatus;
use App\Payments\PaymentService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

/**
 * Règles communes à tous les paiements : idempotence, expiration, paiement tardif, double paiement.
 */
class PaymentServiceTest extends TestCase
{
    private FakePaymentGateway $gateway;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([PaymentStatusUpdated::class]);
        $this->gateway = FakePaymentGateway::install();
        $this->driver = Driver::factory()->create(['current_balance' => 1000, 'old_balance' => 1000]);
    }

    private function service(): PaymentService
    {
        return app(PaymentService::class);
    }

    /** Facture de dépôt portefeuille, comme la crée DriverAPIController::depositBalance. */
    private function depositInvoice(float $amount): Invoice
    {
        $transaction = Transaction::create([
            'user_id' => $this->driver->id,
            'status' => Transaction::CREATED,
            'type' => Transaction::TYPE_DEPOSIT,
            'user_source' => 'drivers',
            'currency_code' => 'XOF',
            'amount' => $amount,
            'is_in' => true,
        ]);

        return Invoice::create([
            'order_id' => $transaction->id,
            'customer_id' => $this->driver->id,
            'order_source' => 'transactions',
            'reference' => 'WALLET-'.$transaction->id,
            'subtotal' => $amount,
            'total' => $amount,
            'status' => Invoice::UNPAID,
            'currency_code' => 'XOF',
        ]);
    }

    private function success(int $amount): GatewayPaymentStatus
    {
        return new GatewayPaymentStatus(GatewayPaymentStatus::SUCCESSFUL, $amount);
    }

    /** @test */
    public function concurrent_success_applies_effects_once()
    {
        $payment = $this->service()->start($this->depositInvoice(5000), $this->driver->id, 'wave', 'drivers');

        // Webhook et polling chargent chacun leur copie du paiement avant de l'appliquer
        $fromWebhook = Payment::find($payment->id);
        $fromPolling = Payment::find($payment->id);
        $this->service()->applyStatus($fromWebhook, $this->success(5000));
        $this->service()->applyStatus($fromPolling, $this->success(5000));

        $this->assertEquals(6000, $this->driver->fresh()->current_balance);
        Event::assertDispatchedTimes(PaymentStatusUpdated::class, 1);
    }

    /** @test */
    public function the_amount_is_the_invoice_total_rounded_up()
    {
        $payment = $this->service()->start($this->depositInvoice(4999.2), $this->driver->id, 'wave', 'drivers');

        $this->assertEquals(5000, $payment->amount);
    }

    /** @test */
    public function an_expired_pending_payment_is_marked_expired()
    {
        $payment = $this->service()->start($this->depositInvoice(5000), $this->driver->id, 'wave', 'drivers');
        $this->travel(31)->minutes();

        $this->service()->refresh($payment);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_EXPIRED, $payment->status);
        $this->assertSame(1, $payment->check_attempts);
        $this->assertNotNull($payment->last_checked_at);
    }

    /** @test */
    public function a_pending_payment_before_expiry_stays_pending()
    {
        $payment = $this->service()->start($this->depositInvoice(5000), $this->driver->id, 'wave', 'drivers');

        $this->service()->refresh($payment);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    /** @test */
    public function a_late_success_after_expiry_is_still_applied()
    {
        $payment = $this->service()->start($this->depositInvoice(5000), $this->driver->id, 'wave', 'drivers');
        $this->travel(31)->minutes();
        $this->service()->refresh($payment);

        $this->gateway->willReturnStatus($this->success(5000));
        $this->service()->refresh($payment->fresh());

        $this->assertSame(Payment::STATUS_SUCCESSFUL, $payment->fresh()->status);
        $this->assertEquals(6000, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function a_failed_payment_is_never_reopened()
    {
        $payment = $this->service()->start($this->depositInvoice(5000), $this->driver->id, 'wave', 'drivers');
        $this->service()->applyStatus($payment, new GatewayPaymentStatus(GatewayPaymentStatus::FAILED));

        $this->service()->applyStatus($payment->fresh(), $this->success(5000));

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertEquals(1000, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function paying_twice_with_the_same_method_reuses_the_pending_payment()
    {
        $invoice = $this->depositInvoice(5000);

        $first = $this->service()->start($invoice, $this->driver->id, 'wave', 'drivers');
        $second = $this->service()->start($invoice, $this->driver->id, 'wave', 'drivers');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $this->gateway->createCalls);
    }

    /** @test */
    public function changing_method_opens_a_new_payment()
    {
        $invoice = $this->depositInvoice(5000);

        $first = $this->service()->start($invoice, $this->driver->id, 'wave', 'drivers');
        $second = $this->service()->start($invoice, $this->driver->id, 'orange', 'drivers');

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, $this->gateway->createCalls);
    }

    /** @test */
    public function a_second_successful_payment_on_a_paid_invoice_has_no_effect_and_alerts()
    {
        Log::spy();
        $invoice = $this->depositInvoice(5000);
        $wave = $this->service()->start($invoice, $this->driver->id, 'wave', 'drivers');
        $orange = $this->service()->start($invoice, $this->driver->id, 'orange', 'drivers');

        $this->service()->applyStatus($wave, $this->success(5000));
        $this->service()->applyStatus($orange, $this->success(5000));

        $this->assertSame(Payment::STATUS_SUCCESSFUL, $orange->fresh()->status);
        $this->assertEquals(6000, $this->driver->fresh()->current_balance);
        Log::shouldHaveReceived('critical')->once();
    }

    /** @test */
    public function a_zero_total_is_paid_without_calling_the_provider()
    {
        $payment = $this->service()->start($this->depositInvoice(0), $this->driver->id, 'wave', 'drivers');

        $this->assertSame(Payment::STATUS_SUCCESSFUL, $payment->fresh()->status);
        $this->assertNull($payment->provider);
        $this->assertSame(0, $this->gateway->createCalls);
        $this->assertSame(Invoice::PAID, Invoice::find($payment->invoice_id)->status);
    }

    /** @test */
    public function the_payment_methods_of_the_active_provider_are_listed()
    {
        $this->getJson('/api/v1/payments/methods')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'wave')
            ->assertJsonPath('data.1.code', 'orange');
    }

    /** @test */
    public function the_payment_methods_return_503_without_active_provider()
    {
        Setting::where('name', 'PAYMENT_ACTIVE_PROVIDER')->delete();
        Setting::flushCache();

        $this->getJson('/api/v1/payments/methods')->assertStatus(503);
    }
}
