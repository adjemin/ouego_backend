<?php

namespace Tests\Feature\Payments;

use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Dépôt / retrait sur le portefeuille chauffeur, puis confirmation par le webhook
 * de l'agrégateur de paiement. Le solde ne doit bouger qu'au webhook SUCCESSFUL, une seule fois.
 */
class DriverWalletPaymentFlowTest extends TestCase
{
    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->driver = Driver::factory()->create(['current_balance' => 1000, 'old_balance' => 1000]);
    }

    /** @test */
    public function a_deposit_creates_a_pending_payment_without_touching_the_balance()
    {
        $payment = $this->deposit(5000);

        $this->assertSame(Payment::STATUS_INITIATED, $payment->status);
        $this->assertTrue((bool) $payment->is_waiting);
        $this->assertEquals(5000, $payment->amount);

        $invoice = Invoice::find($payment->invoice_id);
        $this->assertSame(Invoice::UNPAID, $invoice->status);
        $this->assertSame('transactions', $invoice->order_source);

        $transaction = Transaction::find($invoice->order_id);
        $this->assertSame(Transaction::TYPE_DEPOSIT, $transaction->type);
        $this->assertSame(Transaction::CREATED, $transaction->status);

        $this->assertEquals(1000, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function a_successful_webhook_credits_the_driver_balance()
    {
        $payment = $this->deposit(5000);

        $this->webhook($payment->payment_reference, Payment::STATUS_SUCCESSFUL)
            ->assertOk()
            ->assertJson(['status' => 'OK']);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_SUCCESSFUL, $payment->status);
        $this->assertFalse((bool) $payment->is_waiting);
        $this->assertTrue((bool) $payment->is_completed);

        $invoice = Invoice::find($payment->invoice_id);
        $this->assertSame(Invoice::PAID, $invoice->status);
        $this->assertSame(Transaction::SUCCESSFUL, Transaction::find($invoice->order_id)->status);

        $this->assertEquals(6000, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function a_replayed_successful_webhook_credits_only_once()
    {
        $payment = $this->deposit(5000);

        $this->webhook($payment->payment_reference, Payment::STATUS_SUCCESSFUL)->assertJson(['status' => 'OK']);
        $this->webhook($payment->payment_reference, Payment::STATUS_SUCCESSFUL)->assertJson(['status' => 'KO']);

        $this->assertEquals(6000, $this->driver->fresh()->current_balance);
    }

    /**
     * @test
     * @dataProvider unsuccessfulStatuses
     */
    public function an_unsuccessful_webhook_leaves_the_balance_unchanged(string $status)
    {
        $payment = $this->deposit(5000);

        $this->webhook($payment->payment_reference, $status)->assertOk();

        $this->assertNull(Payment::withTrashed()->find($payment->id), 'Le paiement échoué est supprimé');
        $this->assertSame(Invoice::UNPAID, Invoice::find($payment->invoice_id)->status);
        $this->assertEquals(1000, $this->driver->fresh()->current_balance);
    }

    public static function unsuccessfulStatuses(): array
    {
        return [
            'failed' => [Payment::STATUS_FAILED],
            'cancelled' => [Payment::STATUS_CANCELLED],
            'expired' => [Payment::STATUS_EXPIRED],
        ];
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
            ->postJson('/api/v1/drivers/transactions/balance/deposit', ['amount' => 50])
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

        $reference = $this->actingAs($this->driver, 'api-drivers')
            ->postJson('/api/v1/drivers/transactions/balance/deposit', ['driver_id' => $other->id, 'amount' => 5000])
            ->assertOk()
            ->assertJsonPath('data.user_id', $this->driver->id)
            ->json('data.payment_reference');
        $this->app['auth']->forgetGuards();

        $this->webhook($reference, Payment::STATUS_SUCCESSFUL)->assertJson(['status' => 'OK']);

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
            ->postJson('/api/v1/drivers/transactions/balance/deposit', ['amount' => $amount])
            ->assertOk()
            ->json('data.id');

        $this->app['auth']->forgetGuards();

        return Payment::findOrFail($id);
    }

    private function webhook(string $reference, string $status)
    {
        return $this->postJson('/api/v1/payments/webhook', ['transaction_id' => $reference, 'status' => $status]);
    }
}
