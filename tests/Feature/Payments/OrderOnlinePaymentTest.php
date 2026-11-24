<?php

namespace Tests\Feature\Payments;

use App\Models\CustomerNotification;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Payment;
use App\Models\Service;
use App\Payments\Data\GatewayPaymentStatus;
use App\Payments\PaymentService;
use App\Services\DriverAssignmentService;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Orders\Aggregats\AggregatOrderTestCase;
use Tests\Support\FakePaymentGateway;

/**
 * Commande payée en ligne : le client paie chez l'opérateur avant la recherche de chauffeur,
 * et c'est la confirmation du paiement qui lance cette recherche.
 */
class OrderOnlinePaymentTest extends AggregatOrderTestCase
{
    /** 25 t de gravier à 6500 + 75000 de livraison (voir GravierOrderFlowTest). */
    private const INVOICE_TOTAL = 237500;

    private Order $order;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        $this->gateway = FakePaymentGateway::install();
        $this->order = $this->makeOrder('online');
    }

    private function makeOrder(string $paymentMethod): Order
    {
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
        ], $paymentMethod)->assertOk()->json('data.id');

        $this->app['auth']->forgetGuards();

        return Order::findOrFail($orderId);
    }

    /** @test */
    public function the_payment_amount_is_the_rounded_invoice_total()
    {
        $this->assertEquals(self::INVOICE_TOTAL, $this->order->invoice->total);

        $this->pay()
            ->assertOk()
            ->assertJsonPath('data.amount', self::INVOICE_TOTAL)
            ->assertJsonPath('data.currency_code', 'XOF')
            ->assertJsonPath('data.status', Payment::STATUS_PENDING)
            ->assertJsonPath('data.invoice_id', $this->order->invoice->id)
            ->assertJsonPath('data.payment_url', fn ($url) => str_starts_with($url, 'https://pay.fake/'));
    }

    /** @test */
    public function a_decimal_total_is_rounded_up()
    {
        $this->order->invoice->update(['total' => 237499.4]);

        $this->pay()->assertOk()->assertJsonPath('data.amount', 237500);
    }

    /** @test */
    public function paying_twice_reuses_the_pending_payment()
    {
        $first = $this->pay()->assertOk()->json('data.id');
        $second = $this->pay()->assertOk()->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, $this->gateway->createCalls);
    }

    /** @test */
    public function a_successful_payment_starts_the_driver_lookup_once()
    {
        $this->mock(DriverAssignmentService::class)->shouldReceive('sendInvitations')->once();
        $payment = Payment::findOrFail($this->pay()->json('data.id'));

        $this->confirm($payment);
        $this->confirm($payment);

        $order = $this->order->fresh();
        $this->assertSame(Order::PERFORMER_LOOKUP, $order->status);
        $this->assertFalse((bool) $order->is_draft);
        $this->assertSame(1, OrderHistory::where(['order_id' => $order->id, 'status' => Order::PERFORMER_LOOKUP])->count());
        $this->assertSame(Invoice::PAID, $order->invoice->status);
        $this->assertTrue((bool) $order->invoice->is_paid_by_customer);
        $this->assertSame(1, CustomerNotification::where('customer_id', $order->customer_id)->count());
    }

    /** @test */
    public function a_failed_payment_leaves_the_order_waiting_for_payment()
    {
        $payment = Payment::findOrFail($this->pay()->json('data.id'));

        app(PaymentService::class)->applyStatus($payment, new GatewayPaymentStatus(GatewayPaymentStatus::FAILED));

        $this->assertSame(Order::INITIATED, $this->order->fresh()->status);
        $this->pay()->assertOk();
        $this->assertSame(2, $this->gateway->createCalls);
    }

    /** @test */
    public function an_unpaid_online_order_cannot_be_confirmed()
    {
        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$this->order->id}/confirm")
            ->assertStatus(400)
            ->assertJsonPath('message', 'Veuillez d\'abord payer la commande');

        $this->assertSame(Order::INITIATED, $this->order->fresh()->status);
    }

    /** @test */
    public function an_unpaid_online_order_cannot_trigger_a_driver_lookup()
    {
        $this->mock(DriverAssignmentService::class)->shouldNotReceive('sendInvitations');

        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$this->order->id}/perform_driver_lookup")
            ->assertStatus(400);
    }

    /** @test */
    public function a_mobile_money_order_is_treated_as_online()
    {
        $order = $this->makeOrder('mobile-money');

        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$order->id}/confirm")
            ->assertStatus(400);
    }

    /** @test */
    public function a_cash_order_cannot_be_paid_online()
    {
        $order = $this->makeOrder('cash');

        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$order->id}/pay", ['payment_method' => 'wave'])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Cette commande se paie en espèces');

        $this->assertSame(0, Payment::count());
    }

    /** @test */
    public function a_cancelled_order_cannot_be_paid()
    {
        $this->order->update(['status' => Order::CANCELLED, 'is_completed' => true]);

        $this->pay()->assertStatus(400)->assertJsonPath('message', 'Cette commande ne peut plus être payée');

        $this->assertSame(0, Payment::count());
    }

    /** @test */
    public function an_amount_above_the_cap_is_refused()
    {
        $this->gateway->maxAmount = 100000;

        $this->pay()->assertStatus(400)
            ->assertJsonPath('message', 'Montant trop élevé pour le paiement en ligne, veuillez payer en espèces');

        $this->assertSame(0, Payment::count());
    }

    /** @test */
    public function a_zero_total_order_is_paid_without_calling_the_provider()
    {
        $this->mock(DriverAssignmentService::class)->shouldReceive('sendInvitations')->once();
        $this->order->invoice->update(['total' => 0]);

        $this->pay()->assertOk()->assertJsonPath('data.status', Payment::STATUS_SUCCESSFUL);

        $this->assertSame(0, $this->gateway->createCalls);
        $this->assertSame(Order::PERFORMER_LOOKUP, $this->order->fresh()->status);
    }

    /** @test */
    public function a_paid_invoice_cannot_be_paid_again()
    {
        $this->order->invoice->update(['status' => Invoice::PAID]);

        $this->pay()->assertStatus(400);

        $this->assertSame(0, Payment::count());
    }

    /** @test */
    public function a_payment_without_method_is_refused()
    {
        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$this->order->id}/pay")
            ->assertStatus(422);
    }

    /** @test */
    public function a_gateway_error_returns_502_and_marks_the_payment_failed()
    {
        $this->gateway->willFailCreate();

        $this->pay()->assertStatus(502);

        $this->assertSame(Payment::STATUS_FAILED, Payment::sole()->status);
        $this->assertSame(Invoice::UNPAID, $this->order->invoice->fresh()->status);
    }

    private function confirm(Payment $payment): void
    {
        app(PaymentService::class)->applyStatus(
            $payment->fresh(),
            new GatewayPaymentStatus(GatewayPaymentStatus::SUCCESSFUL, (int) $payment->amount)
        );
    }

    private function pay()
    {
        return $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$this->order->id}/pay", ['payment_method' => 'wave']);
    }
}
