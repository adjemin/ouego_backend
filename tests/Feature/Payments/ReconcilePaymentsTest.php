<?php

namespace Tests\Feature\Payments;

use App\Models\Customer;
use App\Models\CustomerNotification;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Setting;
use App\Payments\Data\GatewayPaymentStatus;
use App\Services\DriverAssignmentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

/**
 * payments:reconcile : seul moyen de voir les échecs Jèko (aucun webhook d'échec), filet si un webhook se perd,
 * et annulation des commandes en ligne jamais payées.
 */
class ReconcilePaymentsTest extends TestCase
{
    private FakePaymentGateway $gateway;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        Carbon::setTestNow('2026-09-22 10:00:00');
        $this->gateway = FakePaymentGateway::install();
        $this->customer = Customer::create(['name' => 'Client', 'phone' => '2250700000099', 'is_active' => true]);
        $this->mock(DriverAssignmentService::class)->shouldReceive('sendInvitations');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function onlineOrder(int $minutesAgo, string $paymentMethod = 'online'): Order
    {
        $order = Order::create([
            'reference' => Order::generateReference(),
            'customer_id' => $this->customer->id,
            'service_slug' => Service::COURSE,
            'status' => Order::INITIATED,
            'payment_method_code' => $paymentMethod,
            'currency_code' => 'XOF',
            'is_completed' => false,
            'is_waiting' => true,
        ]);
        $order->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save();

        Invoice::create([
            'order_id' => $order->id, 'customer_id' => $this->customer->id, 'reference' => 'ORDER-'.$order->id,
            'total' => 5000, 'status' => Invoice::UNPAID, 'currency_code' => 'XOF',
        ]);

        return $order;
    }

    private function pendingPayment(Order $order, array $attributes = []): Payment
    {
        return Payment::create($attributes + [
            'invoice_id' => $order->orderInvoice()->id, 'user_id' => $this->customer->id,
            'payment_method_code' => 'wave', 'payment_reference' => Payment::generateReference(),
            'amount' => 5000, 'currency_code' => 'XOF', 'status' => Payment::STATUS_PENDING,
            'is_waiting' => true, 'is_completed' => false, 'provider' => 'fake',
            'provider_payment_id' => 'fake-'.uniqid(), 'expires_at' => now()->addMinutes(30),
        ]);
    }

    private function reconcile(): void
    {
        $this->artisan('payments:reconcile')->assertSuccessful();
    }

    /** @test */
    public function a_pending_payment_reported_failed_is_marked_failed()
    {
        $payment = $this->pendingPayment($this->onlineOrder(2));
        $this->gateway->willReturnStatus(new GatewayPaymentStatus(GatewayPaymentStatus::FAILED));

        $this->reconcile();

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
    }

    /** @test */
    public function a_missed_success_is_applied()
    {
        $order = $this->onlineOrder(2);
        $payment = $this->pendingPayment($order);
        $this->gateway->willReturnStatus(new GatewayPaymentStatus(GatewayPaymentStatus::SUCCESSFUL, 5000));

        $this->reconcile();

        $this->assertSame(Payment::STATUS_SUCCESSFUL, $payment->fresh()->status);
        $this->assertSame(Order::PERFORMER_LOOKUP, $order->fresh()->status);
    }

    /** @test */
    public function a_payment_past_expiry_is_expired()
    {
        $payment = $this->pendingPayment($this->onlineOrder(2), ['expires_at' => now()->subMinute()]);

        $this->reconcile();

        $this->assertSame(Payment::STATUS_EXPIRED, $payment->fresh()->status);
    }

    /** @test */
    public function recently_checked_payments_are_skipped()
    {
        $order = $this->onlineOrder(2);
        $this->pendingPayment($order, ['last_checked_at' => now()->subSeconds(30)]);
        // Paiement de plus de 10 min : contrôlé toutes les 5 min seulement
        $old = $this->pendingPayment($order, ['last_checked_at' => now()->subMinutes(3)]);
        $old->forceFill(['created_at' => now()->subMinutes(15)])->save();

        $this->reconcile();

        $this->assertSame(0, $this->gateway->statusCalls);
    }

    /** @test */
    public function due_payments_are_checked()
    {
        $order = $this->onlineOrder(2);
        $this->pendingPayment($order);
        $this->pendingPayment($order, ['last_checked_at' => now()->subMinutes(2)]);
        $old = $this->pendingPayment($order, ['last_checked_at' => now()->subMinutes(6)]);
        $old->forceFill(['created_at' => now()->subMinutes(15)])->save();

        $this->reconcile();

        $this->assertSame(3, $this->gateway->statusCalls);
    }

    /** @test */
    public function one_provider_error_does_not_stop_the_run()
    {
        $order = $this->onlineOrder(2);
        $this->pendingPayment($order);
        $this->pendingPayment($order);
        $this->gateway->failStatus = true;

        $this->reconcile();

        $this->assertSame(2, $this->gateway->statusCalls);
    }

    /** @test */
    public function an_unpaid_online_order_is_cancelled_after_the_timeout()
    {
        $order = $this->onlineOrder(31);

        $this->reconcile();

        $order->refresh();
        $this->assertSame(Order::CANCELLED, $order->status);
        $this->assertTrue((bool) $order->is_completed);
        $this->assertSame(1, OrderHistory::where(['order_id' => $order->id, 'status' => Order::CANCELLED])->count());
        $this->assertSame(1, CustomerNotification::where('customer_id', $this->customer->id)->count());
    }

    /** @test */
    public function the_timeout_is_read_from_the_settings()
    {
        Setting::set('ONLINE_ORDER_PAYMENT_TIMEOUT_MINUTES', '60');
        Setting::flushCache(); // Event::fake() neutralise aussi l'événement Eloquent qui vide le cache
        $order = $this->onlineOrder(31);

        $this->reconcile();

        $this->assertSame(Order::INITIATED, $order->fresh()->status);
    }

    /** @test */
    public function a_recent_unpaid_order_is_not_cancelled()
    {
        $order = $this->onlineOrder(10);

        $this->reconcile();

        $this->assertSame(Order::INITIATED, $order->fresh()->status);
    }

    /** @test */
    public function an_order_with_a_live_pending_payment_is_not_cancelled()
    {
        $order = $this->onlineOrder(31);
        $this->pendingPayment($order, ['last_checked_at' => now()]);

        $this->reconcile();

        $this->assertSame(Order::INITIATED, $order->fresh()->status);
    }

    /** @test */
    public function cash_orders_are_never_cancelled_by_reconcile()
    {
        $order = $this->onlineOrder(120, 'cash');

        $this->reconcile();

        $this->assertSame(Order::INITIATED, $order->fresh()->status);
    }

    /** @test */
    public function a_paid_order_is_not_cancelled()
    {
        $order = $this->onlineOrder(31);
        $order->orderInvoice()->update(['status' => Invoice::PAID]);

        $this->reconcile();

        $this->assertSame(Order::INITIATED, $order->fresh()->status);
    }
}
