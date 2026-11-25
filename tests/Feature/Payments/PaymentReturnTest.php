<?php

namespace Tests\Feature\Payments;

use App\Models\Invoice;
use App\Models\Payment;
use Tests\TestCase;

/**
 * Page de retour après la page de paiement de l'opérateur : renvoie vers l'app, sans aucun effet sur le paiement.
 */
class PaymentReturnTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.return_deeplinks.customers' => 'ouego://payment-return',
            'payments.return_deeplinks.drivers' => 'ouegodriver://payment-return',
        ]);
    }

    private function payment(?string $orderSource): Payment
    {
        $invoice = Invoice::create([
            'order_id' => 1, 'customer_id' => 1, 'order_source' => $orderSource,
            'reference' => 'INV-1', 'total' => 5000, 'status' => Invoice::UNPAID, 'currency_code' => 'XOF',
        ]);

        return Payment::create([
            'invoice_id' => $invoice->id, 'user_id' => 1, 'payment_reference' => 'OUEGO-RET-1',
            'amount' => 5000, 'status' => Payment::STATUS_PENDING, 'provider' => 'jeko',
        ]);
    }

    /** @test */
    public function the_return_page_redirects_to_the_customer_deeplink_and_changes_nothing()
    {
        $payment = $this->payment(null);

        $this->get('/payments/return/OUEGO-RET-1/success')
            ->assertOk()
            ->assertSee('ouego://payment-return?reference=OUEGO-RET-1&amp;result=success', false)
            ->assertSee('Paiement reçu');

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    /** @test */
    public function a_wallet_deposit_returns_to_the_driver_app()
    {
        $this->payment('transactions');

        $this->get('/payments/return/OUEGO-RET-1/error')
            ->assertOk()
            ->assertSee('ouegodriver://payment-return?reference=OUEGO-RET-1&amp;result=error', false)
            ->assertSee('Paiement non abouti');
    }

    /** @test */
    public function an_unknown_reference_shows_the_page_without_deeplink()
    {
        $this->get('/payments/return/OUEGO-NOPE/success')
            ->assertOk()
            ->assertDontSee('payment-return');
    }

    /** @test */
    public function an_unknown_result_is_not_found()
    {
        $this->get('/payments/return/OUEGO-RET-1/whatever')->assertNotFound();
    }
}
