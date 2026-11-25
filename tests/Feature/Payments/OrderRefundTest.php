<?php

namespace Tests\Feature\Payments;

use App\Models\CustomerNotification;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Service;
use App\Payments\Data\GatewayPaymentStatus;
use App\Payments\Data\GatewayRefundResult;
use App\Payments\PaymentService;
use App\Services\DriverAssignmentService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Orders\Aggregats\AggregatOrderTestCase;
use Tests\Support\FakePaymentGateway;

/**
 * Remboursement automatique, intégral, par l'opérateur d'origine, d'une commande payée en ligne puis annulée.
 * Les frais de transfert sont à la charge d'OUEGO : le client reçoit exactement ce qu'il a payé.
 */
class OrderRefundTest extends AggregatOrderTestCase
{
    /** 25 t de gravier à 6500 + 75000 de livraison (voir GravierOrderFlowTest). */
    private const PAID = 237500;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        $this->gateway = FakePaymentGateway::install();
        $this->mock(DriverAssignmentService::class)->shouldReceive('sendInvitations');
    }

    private function makeOrder(string $paymentMethod = 'online'): Order
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

    private function startPayment(Order $order): Payment
    {
        return Payment::findOrFail($this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$order->id}/pay", ['payment_method' => 'wave'])
            ->assertOk()->json('data.id'));
    }

    private function confirm(Payment $payment): void
    {
        app(PaymentService::class)->applyStatus($payment->fresh(), new GatewayPaymentStatus(
            GatewayPaymentStatus::SUCCESSFUL, (int) $payment->amount, '+2250700000001', 'wave'
        ));
    }

    private function paidOrder(): Order
    {
        $order = $this->makeOrder();
        $this->confirm($this->startPayment($order));

        return $order->fresh();
    }

    private function cancel(Order $order)
    {
        return $this->actingAs($this->customer, 'api-customers')->putJson("/api/v1/orders/{$order->id}/cancel");
    }

    private function reconcile(): void
    {
        $this->artisan('payments:reconcile')->assertSuccessful();
    }

    /** @test */
    public function cancelling_a_paid_online_order_refunds_the_full_amount()
    {
        $order = $this->paidOrder();

        $this->cancel($order)->assertOk();

        $refund = PaymentRefund::sole();
        $this->assertSame(self::PAID, $refund->amount);
        $this->assertSame($order->id, $refund->order_id);
        $this->assertSame('fake', $refund->provider);
        $this->assertSame('RF-'.Payment::sole()->payment_reference, $refund->reference);
        $this->assertSame('+2250700000001', $refund->recipient_phone);
        $this->assertSame('wave', $refund->recipient_method);
        $this->assertSame(PaymentRefund::PROCESSING, $refund->status);
        $this->assertSame(1, $this->gateway->refundCalls);

        $order->refresh();
        $this->assertSame(Order::CANCELLED_WITH_PAYMENT, $order->status);
        $this->assertSame(Invoice::REFUNDED, $order->orderInvoice()->status);
    }

    /** @test */
    public function cancelling_twice_refunds_once()
    {
        $order = $this->paidOrder();

        $this->cancel($order);
        $this->cancel($order);
        $this->reconcile();

        $this->assertSame(1, PaymentRefund::count());
        $this->assertSame(1, $this->gateway->refundCalls);
    }

    /** @test */
    public function cancelling_an_unpaid_online_order_creates_no_refund()
    {
        $order = $this->makeOrder();
        $this->startPayment($order);

        $this->cancel($order)->assertOk();

        $this->assertSame(0, PaymentRefund::count());
        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
    }

    /** @test */
    public function cancelling_a_cash_order_creates_no_refund()
    {
        $order = $this->makeOrder('cash');

        $this->cancel($order)->assertOk();

        $this->assertSame(0, PaymentRefund::count());
        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
    }

    /** @test */
    public function a_payment_confirmed_after_cancellation_is_refunded()
    {
        $order = $this->makeOrder();
        $payment = $this->startPayment($order);
        $this->cancel($order)->assertOk();

        $this->confirm($payment);

        $order->refresh();
        $this->assertSame(Order::CANCELLED_WITH_PAYMENT, $order->status);
        $this->assertSame(Invoice::REFUNDED, $order->orderInvoice()->status);
        $this->assertSame(self::PAID, PaymentRefund::sole()->amount);
    }

    /** @test */
    public function a_second_payment_on_a_paid_order_is_refunded()
    {
        $order = $this->makeOrder();
        $first = $this->startPayment($order);
        $second = $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$order->id}/pay", ['payment_method' => 'orange'])->json('data.id');

        $this->confirm($first);
        $this->confirm(Payment::findOrFail($second));

        $refund = PaymentRefund::sole();
        $this->assertSame($second, $refund->payment_id);
        // La commande, payée une fois, continue normalement
        $this->assertSame(Order::PERFORMER_LOOKUP, $order->fresh()->status);
        $this->assertSame(Invoice::PAID, $order->orderInvoice()->status);
    }

    /** @test */
    public function an_order_cancelled_directly_in_database_is_refunded_by_reconcile()
    {
        $order = $this->paidOrder();
        $order->update(['status' => Order::CANCELLED, 'is_completed' => true]);

        $this->reconcile();

        $this->assertSame(1, PaymentRefund::count());
        $this->assertSame(Order::CANCELLED_WITH_PAYMENT, $order->fresh()->status);
    }

    /** @test */
    public function a_failed_refund_is_retried_then_marked_failed_with_an_alert()
    {
        Log::spy();
        $this->gateway->willReturnRefund(new GatewayRefundResult(PaymentRefund::FAILED, null, 'Solde insuffisant'));
        $order = $this->paidOrder();

        $this->cancel($order);
        $refund = PaymentRefund::sole();
        $this->assertSame(PaymentRefund::PENDING, $refund->status);
        $this->assertSame(1, $refund->attempts);

        // Pas de nouvel essai avant l'échéance
        $this->reconcile();
        $this->assertSame(1, $this->gateway->refundCalls);

        foreach (range(1, 4) as $ignored) {
            $this->travel(61)->minutes();
            $this->reconcile();
        }

        $refund->refresh();
        $this->assertSame(5, $this->gateway->refundCalls);
        $this->assertSame(PaymentRefund::FAILED, $refund->status);
        $this->assertSame(5, $refund->attempts);
        $this->assertSame('Solde insuffisant', $refund->last_error);
        // Un refus définitif change la référence de transfert, sinon Jèko répondrait 409 au nouvel essai
        $this->assertCount(5, array_unique($this->gateway->refundReferences));
        Log::shouldHaveReceived('critical')->withArgs(fn ($message) => $message === 'Remboursement en échec')->once();

        $this->travel(61)->minutes();
        $this->reconcile();
        $this->assertSame(5, $this->gateway->refundCalls);
    }

    /** @test */
    public function an_unanswered_refund_is_retried_with_the_same_transfer_reference()
    {
        $this->gateway->failRefund = true;
        $order = $this->paidOrder();

        $this->cancel($order);
        $this->travel(6)->minutes();
        $this->reconcile();

        // Le premier transfert a peut-être été créé : même référence, Jèko dédoublonne
        $this->assertSame(2, $this->gateway->refundCalls);
        $this->assertCount(1, array_unique($this->gateway->refundReferences));
        $this->assertSame(PaymentRefund::PENDING, PaymentRefund::sole()->status);
    }

    /** @test */
    public function a_processing_refund_is_completed_by_reconcile()
    {
        $order = $this->paidOrder();
        $this->cancel($order);
        $this->gateway->nextRefundStatus = new GatewayRefundResult(PaymentRefund::SUCCESSFUL, 'fake-refund-1');

        $this->reconcile();
        $this->reconcile();

        $this->assertSame(PaymentRefund::SUCCESSFUL, PaymentRefund::sole()->status);
        $this->assertSame(1, CustomerNotification::where('customer_id', $this->customer->id)
            ->where('subtitle', 'Votre paiement de 237 500 F a été remboursé')->count());
    }

    /** @test */
    public function a_transfer_reported_failed_is_retried_with_a_new_reference()
    {
        $order = $this->paidOrder();
        $this->cancel($order);
        $this->gateway->nextRefundStatus = new GatewayRefundResult(PaymentRefund::FAILED, 'fake-refund-1', 'error');

        $this->reconcile();
        $refund = PaymentRefund::sole();
        $this->assertSame(PaymentRefund::PENDING, $refund->status);
        $this->assertNull($refund->provider_refund_id);

        $this->gateway->nextRefundStatus = null;
        $this->travel(6)->minutes();
        $this->reconcile();

        $this->assertSame(2, $this->gateway->refundCalls);
        $this->assertCount(2, array_unique($this->gateway->refundReferences));
    }

    /** @test */
    public function a_transfer_webhook_completes_the_refund()
    {
        $order = $this->paidOrder();
        $this->cancel($order);
        $refund = PaymentRefund::sole();
        $this->gateway->nextRefundStatus = new GatewayRefundResult(PaymentRefund::SUCCESSFUL, $refund->provider_refund_id);

        $this->postJson('/api/v1/payments/webhook/fake', [
            'provider_payment_id' => $refund->provider_refund_id,
            'reference' => $refund->providerReference(),
            'kind' => 'transfer',
        ], ['X-Fake-Signature' => 'ok'])->assertOk();

        $this->assertSame(PaymentRefund::SUCCESSFUL, $refund->fresh()->status);
    }

    /** @test */
    public function a_transfer_webhook_attaches_a_transfer_known_only_by_reference()
    {
        // Jèko a répondu 409 : le transfert existe mais son id nous est inconnu
        $this->gateway->willReturnRefund(new GatewayRefundResult(PaymentRefund::PROCESSING, null));
        $order = $this->paidOrder();
        $this->cancel($order);
        $refund = PaymentRefund::sole();
        $this->gateway->nextRefundStatus = new GatewayRefundResult(PaymentRefund::SUCCESSFUL, 'wth_found');

        $this->postJson('/api/v1/payments/webhook/fake', [
            'provider_payment_id' => 'wth_found',
            'reference' => $refund->providerReference(),
            'kind' => 'transfer',
        ], ['X-Fake-Signature' => 'ok'])->assertOk();

        $refund->refresh();
        $this->assertSame('wth_found', $refund->provider_refund_id);
        $this->assertSame(PaymentRefund::SUCCESSFUL, $refund->status);
    }

    // ---------------------------------------------------------------
    // Relecture finale : argent reçu sans issue
    // ---------------------------------------------------------------

    /** @test */
    public function a_success_reported_on_a_failed_payment_is_refunded_with_an_alert()
    {
        Log::spy();
        $order = $this->makeOrder();
        $payment = $this->startPayment($order);
        app(PaymentService::class)->applyStatus($payment, new GatewayPaymentStatus(GatewayPaymentStatus::FAILED));

        $this->confirm($payment);

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $refund = PaymentRefund::sole();
        $this->assertSame($payment->id, $refund->payment_id);
        $this->assertSame(self::PAID, $refund->amount);
        $this->assertSame('+2250700000001', $refund->recipient_phone);
        $this->assertSame(Order::INITIATED, $order->fresh()->status);
        Log::shouldHaveReceived('critical')->withArgs(fn ($message) => str_contains($message, 'déjà clos'))->once();
    }

    /** @test */
    public function a_short_amount_is_refunded_for_what_was_received()
    {
        $order = $this->makeOrder();
        $payment = $this->startPayment($order);

        app(PaymentService::class)->applyStatus($payment, new GatewayPaymentStatus(
            GatewayPaymentStatus::SUCCESSFUL, 2375, '+2250700000001', 'wave'
        ));

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame(2375, PaymentRefund::sole()->amount);
        $this->assertSame(Invoice::UNPAID, $order->orderInvoice()->status);
    }

    /** @test */
    public function an_expired_payment_is_still_polled_for_a_late_success()
    {
        $order = $this->makeOrder();
        $payment = $this->startPayment($order);
        $this->travel(31)->minutes();
        app(PaymentService::class)->refresh($payment);
        $this->assertSame(Payment::STATUS_EXPIRED, $payment->fresh()->status);

        $this->travel(2)->hours();
        $this->gateway->willReturnStatus(new GatewayPaymentStatus(GatewayPaymentStatus::SUCCESSFUL, self::PAID, '+2250700000001', 'wave'));
        $this->reconcile();

        $this->assertSame(Payment::STATUS_SUCCESSFUL, $payment->fresh()->status);
    }

    /** @test */
    public function a_refund_stuck_in_processing_alerts_once()
    {
        Log::spy();
        $this->gateway->willReturnRefund(new GatewayRefundResult(PaymentRefund::PROCESSING, null));
        $this->cancel($this->paidOrder());

        $this->travel(25)->hours();
        $this->reconcile();
        $this->reconcile();

        Log::shouldHaveReceived('critical')->withArgs(fn ($message) => $message === 'Remboursement bloqué en cours de transfert')->once();
    }

    /** @test */
    public function a_payment_on_an_order_failed_by_the_dashboard_is_refunded()
    {
        $order = $this->makeOrder();
        $payment = $this->startPayment($order);
        $order->update(['status' => Order::FAILED, 'is_completed' => true]);

        $this->confirm($payment);

        $this->assertSame(self::PAID, PaymentRefund::sole()->amount);
        $this->assertSame(Order::FAILED, $order->fresh()->status);
    }

    /** @test */
    public function confirm_does_not_rewind_an_order_a_driver_accepted()
    {
        $order = $this->paidOrder();
        $order->update(['status' => Order::ACCEPTED, 'driver_id' => \App\Models\Driver::factory()->create()->id]);

        $this->actingAs($this->customer, 'api-customers')->putJson("/api/v1/orders/{$order->id}/confirm");

        $this->assertSame(Order::ACCEPTED, $order->fresh()->status);
    }

    /** @test */
    public function a_closed_legacy_not_found_order_cannot_be_relaunched()
    {
        $order = $this->makeOrder('cash');
        $order->update(['status' => Order::PERFORMER_NOT_FOUND, 'is_completed' => true, 'is_draft' => false]);

        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$order->id}/perform_driver_lookup")
            ->assertStatus(400);

        $this->assertSame(Order::PERFORMER_NOT_FOUND, $order->fresh()->status);
    }

    /** @test */
    public function an_error_after_the_payment_is_applied_still_answers_200_to_the_webhook()
    {
        $order = $this->makeOrder();
        $payment = $this->startPayment($order);
        $this->mock(DriverAssignmentService::class)->shouldReceive('sendInvitations')->andThrow(new \RuntimeException('FCM down'));
        $this->gateway->willReturnStatus(new GatewayPaymentStatus(GatewayPaymentStatus::SUCCESSFUL, self::PAID));

        $this->postJson('/api/v1/payments/webhook/fake', ['provider_payment_id' => $payment->provider_payment_id], ['X-Fake-Signature' => 'ok'])
            ->assertOk();

        $this->assertSame(Payment::STATUS_SUCCESSFUL, $payment->fresh()->status);
    }
}
