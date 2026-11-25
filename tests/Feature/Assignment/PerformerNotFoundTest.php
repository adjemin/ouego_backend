<?php

namespace Tests\Feature\Assignment;

use App\Jobs\ProcessPendingOrderAssignments;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Payment;
use App\Models\Service;
use App\Services\DriverAssignmentService;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Orders\Aggregats\AggregatOrderTestCase;

/**
 * « Aucun chauffeur trouvé » n'est pas une fin : la commande reste ouverte, le client peut relancer
 * la recherche autant de fois qu'il veut, jusqu'à annuler lui-même.
 */
class PerformerNotFoundTest extends AggregatOrderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    private function notFoundOrder(string $paymentMethod = 'cash'): Order
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

        $order = Order::findOrFail($orderId);
        if ($paymentMethod !== 'cash') {
            $order->orderInvoice()->update(['status' => Invoice::PAID, 'is_paid_by_customer' => true]);
        }
        $order->update([
            'status' => Order::PERFORMER_LOOKUP,
            'order_date' => now()->subHour(),
            'is_draft' => false,
        ]);

        (new ProcessPendingOrderAssignments())->handle();

        $order->refresh();
        $this->assertSame(Order::PERFORMER_NOT_FOUND, $order->status);

        return $order;
    }

    private function relaunch(Order $order)
    {
        return $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$order->id}/perform_driver_lookup");
    }

    /** @test */
    public function a_not_found_order_stays_open()
    {
        $order = $this->notFoundOrder();

        $this->assertFalse((bool) $order->is_completed);
        $this->assertTrue(Order::whereKey($order->id)->inProgress()->exists());
    }

    /** @test */
    public function the_customer_can_relaunch_a_not_found_order()
    {
        $order = $this->notFoundOrder();
        $this->mock(DriverAssignmentService::class)->shouldReceive('sendInvitations')->once();

        $this->relaunch($order)->assertOk();

        $order->refresh();
        $this->assertSame(Order::PERFORMER_LOOKUP, $order->status);
        $this->assertTrue($order->performerLookupDeadline()->isFuture());
        $this->assertSame(1, OrderHistory::where([
            'order_id' => $order->id, 'status' => Order::PERFORMER_LOOKUP,
            'creator' => 'customers', 'created_id' => $this->customer->id,
        ])->count());
    }

    /** @test */
    public function a_paid_online_not_found_order_relaunches_without_new_payment()
    {
        $order = $this->notFoundOrder('online');
        $this->mock(DriverAssignmentService::class)->shouldReceive('sendInvitations')->once();

        $this->relaunch($order)->assertOk();

        $this->assertSame(Order::PERFORMER_LOOKUP, $order->fresh()->status);
        $this->assertSame(0, Payment::count());
    }

    /** @test */
    public function a_not_found_order_is_not_picked_up_by_the_assignment_job()
    {
        $order = $this->notFoundOrder();
        $this->mock(DriverAssignmentService::class)->shouldNotReceive('sendInvitations');

        (new ProcessPendingOrderAssignments())->handle();

        $this->assertSame(Order::PERFORMER_NOT_FOUND, $order->fresh()->status);
    }

    /** @test */
    public function a_not_found_order_blocks_account_deletion()
    {
        $this->notFoundOrder();

        $this->actingAs($this->customer, 'api-customers')
            ->deleteJson('/api/v1/customers/account')
            ->assertStatus(400);
    }

    /** @test */
    public function a_not_found_order_can_be_cancelled()
    {
        $order = $this->notFoundOrder();

        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$order->id}/cancel")
            ->assertOk();

        $order->refresh();
        $this->assertSame(Order::CANCELLED, $order->status);
        $this->assertTrue((bool) $order->is_completed);
    }
}
