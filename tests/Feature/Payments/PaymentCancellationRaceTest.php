<?php

namespace Tests\Feature\Payments;

use App\Events\CustomerNotificationCreated;
use App\Events\PaymentStatusUpdated;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Service;
use App\Payments\Data\GatewayPaymentStatus;
use App\Payments\PaymentService;
use App\Services\DriverAssignmentService;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Orders\Aggregats\AggregatOrderTestCase;
use Tests\Support\FakePaymentGateway;

/**
 * Le paiement est confirmé pendant que le client annule : l'annulation passe entre la validation du paiement
 * et le lancement de la recherche. La commande doit rester annulée et le paiement être remboursé.
 */
class PaymentCancellationRaceTest extends AggregatOrderTestCase
{
    /** @test */
    public function a_cancellation_landing_before_the_dispatch_keeps_the_order_cancelled_and_refunds()
    {
        Event::fake([CustomerNotificationCreated::class]);
        FakePaymentGateway::install();
        $this->mock(DriverAssignmentService::class)->shouldNotReceive('sendInvitations');

        $orderId = $this->createOrder([
            'service_slug' => Service::AGREGATS_CONSTRUCTION,
            'meta_data' => ['product_type_slug' => 'gravier-515-petit-grain', 'product_slug' => 'gravier', 'delivery_type_code' => 'EXPRESS'],
            'quantity' => 25,
            'delivery_price' => 75000,
            'carrier_id' => $this->carrier->id,
            'route_points' => [$this->destination()],
        ], 'online')->assertOk()->json('data.id');
        $payment = Payment::findOrFail($this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$orderId}/pay", ['payment_method' => 'wave'])->json('data.id'));

        // Diffusé après la validation du paiement, avant le lancement de la recherche
        Event::listen(PaymentStatusUpdated::class, fn () => Order::whereKey($orderId)->update([
            'status' => Order::CANCELLED, 'is_completed' => true,
        ]));

        app(PaymentService::class)->applyStatus($payment, new GatewayPaymentStatus(
            GatewayPaymentStatus::SUCCESSFUL, (int) $payment->amount, '+2250700000001', 'wave'
        ));

        $this->assertSame(Order::CANCELLED_WITH_PAYMENT, Order::find($orderId)->status);
        $this->assertSame(1, PaymentRefund::count());
    }
}
