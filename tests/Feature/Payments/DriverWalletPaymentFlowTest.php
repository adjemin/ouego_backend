<?php

namespace Tests\Feature\Payments;

use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Transaction;
use App\Payments\Data\GatewayPaymentStatus;
use App\Payments\PaymentService;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

/**
 * Dépôt sur le portefeuille chauffeur via l'opérateur actif : le solde ne bouge qu'à la confirmation
 * de l'opérateur, une seule fois. Retrait : confirmé par l'ancien webhook générique, inchangé.
 */
class DriverWalletPaymentFlowTest extends TestCase
{
    private Driver $driver;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->gateway = FakePaymentGateway::install();

        $this->driver = Driver::factory()->create(['current_balance' => 1000, 'old_balance' => 1000]);
    }

    /** @test */
    public function a_deposit_returns_a_payment_url_without_touching_the_balance()
    {
        $payment = $this->deposit(5000);

        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame('fake', $payment->provider);
        $this->assertSame('https://pay.fake/'.$payment->payment_reference, $payment->payment_url);
        $this->assertSame('wave', $payment->payment_method_code);
        $this->assertEquals(5000, $payment->amount);
        $this->assertNotNull($payment->expires_at);

        $invoice = Invoice::find($payment->invoice_id);
        $this->assertSame(Invoice::UNPAID, $invoice->status);
        $this->assertSame('transactions', $invoice->order_source);

        $transaction = Transaction::find($invoice->order_id);
        $this->assertSame(Transaction::TYPE_DEPOSIT, $transaction->type);
        $this->assertSame(Transaction::CREATED, $transaction->status);

        $this->assertEquals(1000, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function a_successful_status_credits_the_driver_once()
    {
        $payment = $this->deposit(5000);

        $this->confirm($payment, new GatewayPaymentStatus(GatewayPaymentStatus::SUCCESSFUL, 5000, '+2250700000000', 'wave'));
        $this->confirm($payment, new GatewayPaymentStatus(GatewayPaymentStatus::SUCCESSFUL, 5000, '+2250700000000', 'wave'));

        $payment->refresh();
        $this->assertSame(Payment::STATUS_SUCCESSFUL, $payment->status);
        $this->assertFalse((bool) $payment->is_waiting);
        $this->assertTrue((bool) $payment->is_completed);
        $this->assertSame('+2250700000000', $payment->payment_gateway_buyer_reference);

        $invoice = Invoice::find($payment->invoice_id);
        $this->assertSame(Invoice::PAID, $invoice->status);
        $this->assertSame(Transaction::SUCCESSFUL, Transaction::find($invoice->order_id)->status);

        $this->assertEquals(6000, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function a_failed_status_leaves_the_balance_and_keeps_the_payment()
    {
        $payment = $this->deposit(5000);

        $this->confirm($payment, new GatewayPaymentStatus(GatewayPaymentStatus::FAILED));

        $payment->refresh();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $invoice = Invoice::find($payment->invoice_id);
        $this->assertSame(Invoice::UNPAID, $invoice->status);
        $this->assertSame(Transaction::FAILED, Transaction::find($invoice->order_id)->status);
        $this->assertEquals(1000, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function a_short_paid_amount_is_not_validated()
    {
        $payment = $this->deposit(5000);

        $this->confirm($payment, new GatewayPaymentStatus(GatewayPaymentStatus::SUCCESSFUL, 4000));

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame(Invoice::UNPAID, Invoice::find($payment->invoice_id)->status);
        $this->assertEquals(1000, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function a_deposit_without_active_provider_returns_503()
    {
        Setting::where('name', 'PAYMENT_ACTIVE_PROVIDER')->delete();
        Setting::flushCache();

        $this->actingAs($this->driver, 'api-drivers')
            ->postJson('/api/v1/drivers/transactions/balance/deposit', ['amount' => 5000, 'payment_method' => 'wave'])
            ->assertStatus(503);

        $this->assertSame(0, Payment::count());
    }

    /** @test */
    public function a_deposit_with_an_unsupported_method_returns_400()
    {
        $this->actingAs($this->driver, 'api-drivers')
            ->postJson('/api/v1/drivers/transactions/balance/deposit', ['amount' => 5000, 'payment_method' => 'bitcoin'])
            ->assertStatus(400);

        $this->assertSame(0, Payment::count());
    }

    /** @test */
    public function a_deposit_without_payment_method_is_refused()
    {
        $this->actingAs($this->driver, 'api-drivers')
            ->postJson('/api/v1/drivers/transactions/balance/deposit', ['amount' => 5000])
            ->assertStatus(422);

        $this->assertSame(0, Payment::count());
    }

    /** @test */
    public function a_gateway_error_returns_502_and_marks_the_payment_failed()
    {
        $this->gateway->willFailCreate();

        $this->actingAs($this->driver, 'api-drivers')
            ->postJson('/api/v1/drivers/transactions/balance/deposit', ['amount' => 5000, 'payment_method' => 'wave'])
            ->assertStatus(502);

        $payment = Payment::sole();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $this->assertSame(Transaction::FAILED, Transaction::find(Invoice::find($payment->invoice_id)->order_id)->status);
        $this->assertEquals(1000, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function a_webhook_with_an_unknown_status_changes_nothing()
    {
        $payment = $this->deposit(5000);

        $this->webhook($payment->payment_reference, 'WHATEVER')
            ->assertOk()
            ->assertJsonPath('error.message', 'MISSING_TRANSACTION_STATUS');

        $this->assertSame(Invoice::UNPAID, Invoice::find($payment->invoice_id)->status);
        $this->assertEquals(1000, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function a_webhook_for_an_unknown_reference_is_ignored()
    {
        $this->webhook('UNKNOWN-REF', Payment::STATUS_SUCCESSFUL)
            ->assertOk()
            ->assertJsonPath('error.message', 'Transaction not found');

        $this->assertEquals(1000, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function a_webhook_without_transaction_id_is_rejected_cleanly()
    {
        $this->postJson('/api/v1/payments/webhook', ['status' => Payment::STATUS_SUCCESSFUL])
            ->assertOk()
            ->assertJsonPath('error.message', 'Transaction not found');
    }

    /** @test */
    public function a_successful_withdrawal_webhook_debits_the_driver_balance()
    {
        $payment = $this->actingAs($this->driver, 'api-drivers')
            ->postJson('/api/v1/drivers/transactions/balance/withdraw', ['amount' => 400])
            ->assertOk()
            ->json('data');

        $this->assertEquals(1000, $this->driver->fresh()->current_balance);

        $this->webhook($payment['payment_reference'], Payment::STATUS_SUCCESSFUL)->assertJson(['status' => 'OK']);

        $this->assertEquals(600, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function a_withdrawal_above_the_balance_is_refused()
    {
        $this->actingAs($this->driver, 'api-drivers')
            ->postJson('/api/v1/drivers/transactions/balance/withdraw', ['amount' => 1500])
            ->assertStatus(400);

        $this->assertSame(0, Payment::count());
        $this->assertEquals(1000, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function a_deposit_below_the_minimum_is_refused()
    {
        $this->actingAs($this->driver, 'api-drivers')
            ->postJson('/api/v1/drivers/transactions/balance/deposit', ['amount' => 50, 'payment_method' => 'wave'])
            ->assertStatus(422);

        $this->assertSame(0, Payment::count());
    }

    public static function walletOperations(): array
    {
        return [
            'dépôt' => ['deposit'],
            'retrait' => ['withdraw'],
        ];
    }

    /**
     * @test
     * @dataProvider walletOperations
     */
    public function a_guest_cannot_create_a_wallet_operation(string $operation)
    {
        $this->postJson("/api/v1/drivers/transactions/balance/{$operation}", ['driver_id' => $this->driver->id, 'amount' => 500])
            ->assertStatus(401);

        $this->assertSame(0, Payment::count());
        $this->assertSame(0, Transaction::count());
    }

    /** @test */
    public function a_deposit_always_goes_to_the_authenticated_driver()
    {
        $other = Driver::factory()->create(['current_balance' => 1000, 'old_balance' => 1000]);

        $id = $this->actingAs($this->driver, 'api-drivers')
            ->postJson('/api/v1/drivers/transactions/balance/deposit', ['driver_id' => $other->id, 'amount' => 5000, 'payment_method' => 'wave'])
            ->assertOk()
            ->assertJsonPath('data.user_id', $this->driver->id)
            ->json('data.id');
        $this->app['auth']->forgetGuards();

        $this->confirm(Payment::findOrFail($id), new GatewayPaymentStatus(GatewayPaymentStatus::SUCCESSFUL, 5000));

        $this->assertEquals(6000, $this->driver->fresh()->current_balance);
        $this->assertEquals(1000, $other->fresh()->current_balance);
    }

    /** @test */
    public function a_driver_cannot_withdraw_from_another_driver_wallet()
    {
        $victim = Driver::factory()->create(['current_balance' => 100000, 'old_balance' => 100000]);

        // Le solde vérifié est celui du chauffeur connecté (1000), pas celui de la victime
        $this->actingAs($this->driver, 'api-drivers')
            ->postJson('/api/v1/drivers/transactions/balance/withdraw', ['driver_id' => $victim->id, 'amount' => 50000])
            ->assertStatus(400);

        $reference = $this->actingAs($this->driver, 'api-drivers')
            ->postJson('/api/v1/drivers/transactions/balance/withdraw', ['driver_id' => $victim->id, 'amount' => 400])
            ->assertOk()
            ->assertJsonPath('data.user_id', $this->driver->id)
            ->json('data.payment_reference');
        $this->app['auth']->forgetGuards();

        $this->webhook($reference, Payment::STATUS_SUCCESSFUL)->assertJson(['status' => 'OK']);

        $this->assertEquals(100000, $victim->fresh()->current_balance);
        $this->assertEquals(600, $this->driver->fresh()->current_balance);
    }

    private function deposit(int $amount): Payment
    {
        $id = $this->actingAs($this->driver, 'api-drivers')
            ->postJson('/api/v1/drivers/transactions/balance/deposit', ['amount' => $amount, 'payment_method' => 'wave'])
            ->assertOk()
            ->json('data.id');

        $this->app['auth']->forgetGuards();

        return Payment::findOrFail($id);
    }

    private function confirm(Payment $payment, GatewayPaymentStatus $status): void
    {
        app(PaymentService::class)->applyStatus($payment, $status);
    }

    private function webhook(string $reference, string $status)
    {
        return $this->postJson('/api/v1/payments/webhook', ['transaction_id' => $reference, 'status' => $status]);
    }
}
