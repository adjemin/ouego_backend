<?php

namespace Tests\Feature\Orders;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Courses (et autres services hors agrégats) : annulation impossible une fois démarrée par le chauffeur.
 */
class CourseOrderCancellationTest extends TestCase
{
    private Customer $customer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->customer = Customer::create(['name' => 'Client', 'phone' => '2250700000012', 'is_active' => true]);

        $this->order = Order::create([
            'reference' => Order::generateReference(),
            'customer_id' => $this->customer->id,
            'service_slug' => Service::COURSE,
            'status' => Order::PERFORMER_LOOKUP,
            'currency_code' => 'XOF',
            'is_draft' => false,
            'is_started' => false,
        ]);
    }

    private function cancel()
    {
        return $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$this->order->id}/cancel");
    }

    /** @test */
    public function it_can_be_cancelled_before_a_driver_is_assigned()
    {
        $this->cancel()->assertOk();

        $this->assertSame(Order::CANCELLED, $this->order->fresh()->status);
    }

    /** @test */
    public function it_can_be_cancelled_when_the_assigned_driver_has_not_started()
    {
        $this->order->update(['driver_id' => Driver::factory()->create()->id, 'status' => Order::PERFORMER_FOUND]);

        $this->cancel()->assertOk();

        $this->assertSame(Order::CANCELLED, $this->order->fresh()->status);
    }

    /** @test */
    public function it_cannot_be_cancelled_once_started_by_the_driver()
    {
        $this->order->update([
            'driver_id' => Driver::factory()->create()->id,
            'status' => Order::PICKUP_ARRIVED,
            'is_started' => true,
        ]);

        $this->cancel()
            ->assertStatus(400)
            ->assertJsonPath('message', 'Vous ne pouvez pas annuler cette commande car elle a commencé');

        $this->assertSame(Order::PICKUP_ARRIVED, $this->order->fresh()->status);
    }
}
