<?php

namespace Tests\Feature\Payments;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Orders\Aggregats\AggregatOrderTestCase;

/**
 * Paiement en ligne d'une commande : le paiement créé porte le montant de la facture,
 * puis le webhook de l'agrégateur marque la facture payée.
 */
class OrderOnlinePaymentTest extends AggregatOrderTestCase
{
    /** 25 t de gravier à 6500 + 75000 de livraison (voir GravierOrderFlowTest). */
    private const INVOICE_TOTAL = 237500;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $orderId = $this->createOrder([
            'service_slug' => Service::AGREGATS_CONSTRUCTION,
            'meta_data' => [
                'product_type_slug' => 'gravier-515-petit-grain',
                'product_slug' => 'gravier',
                'delivery_type_code' => 'EXPRESS',
            ],
            'quantity' => 25,
            'delivery_price' => 75000,
            'carrier_id' => $this->carrier->id,
            'route_points' => [$this->destination()],
        ], 'online')->assertOk()->json('data.id');

        $this->app['auth']->forgetGuards();
        $this->order = Order::findOrFail($orderId);
    }

    /** @test */
    public function the_payment_amount_is_the_invoice_total()
    {
        $this->assertEquals(self::INVOICE_TOTAL, $this->order->invoice->total);

        $this->pay()
            ->assertOk()
            ->assertJsonPath('data.amount', self::INVOICE_TOTAL)
            ->assertJsonPath('data.currency_code', 'XOF')
            ->assertJsonPath('data.status', Payment::STATUS_PENDING)
            ->assertJsonPath('data.invoice_id', $this->order->invoice->id);
    }

    /** @test */
    public function a_successful_webhook_marks_the_invoice_paid()
    {
        $reference = $this->pay()->json('data.payment_reference');
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/payments/webhook', ['transaction_id' => $reference, 'status' => Payment::STATUS_SUCCESSFUL])
            ->assertJson(['status' => 'OK']);

        $this->assertSame(Invoice::PAID, $this->order->invoice->fresh()->status);
    }

    /** @test */
    public function a_paid_invoice_cannot_be_paid_again()
    {
        $this->order->invoice->update(['status' => Invoice::PAID]);

        $this->pay()->assertStatus(400);

        $this->assertSame(0, Payment::count());
    }

    private function pay()
    {
        return $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$this->order->id}/pay");
    }
}
