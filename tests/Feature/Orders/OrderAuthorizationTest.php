<?php

namespace Tests\Feature\Orders;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderInvitation;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderAuthorizationTest extends TestCase
{
    private Customer $owner;

    private Customer $otherCustomer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->owner = Customer::create(['name' => 'Client Propriétaire', 'phone' => '2250700000001', 'is_active' => true]);
        $this->otherCustomer = Customer::create(['name' => 'Autre Client', 'phone' => '2250700000002', 'is_active' => true]);

        $this->order = Order::create([
            'reference' => Order::generateReference(),
            'customer_id' => $this->owner->id,
            'status' => Order::INITIATED,
            'currency_code' => 'XOF',
        ]);
    }

    public static function customerEndpoints(): array
    {
        return [
            'confirm' => ['put', 'confirm', 404],
            'cancel' => ['put', 'cancel', 404],
            'rating' => ['put', 'rating', 404],
            'perform_driver_lookup' => ['put', 'perform_driver_lookup', 400],
            'history' => ['get', 'history', 404],
            'pay' => ['put', 'pay', 404],
        ];
    }

    /**
     * @test
     * @dataProvider customerEndpoints
     */
    public function a_guest_cannot_act_on_an_order(string $method, string $action, int $notFoundStatus)
    {
        $this->json($method, "/api/v1/orders/{$this->order->id}/{$action}", ['rating' => 5])
            ->assertStatus(401);

        $this->assertSame(Order::INITIATED, $this->order->fresh()->status);
    }

    /**
     * @test
     * @dataProvider customerEndpoints
     */
    public function a_customer_cannot_act_on_another_customer_order(string $method, string $action, int $notFoundStatus)
    {
        // Même réponse qu'une commande inexistante, pour ne pas révéler les identifiants des autres clients
        $this->actingAs($this->otherCustomer, 'api-customers')
            ->json($method, "/api/v1/orders/{$this->order->id}/{$action}", ['rating' => 5])
            ->assertStatus($notFoundStatus);

        $order = $this->order->fresh();
        $this->assertSame(Order::INITIATED, $order->status);
        $this->assertNull($order->rating);
    }

    /** @test */
    public function the_owner_can_cancel_their_order()
    {
        $this->actingAs($this->owner, 'api-customers')
            ->putJson("/api/v1/orders/{$this->order->id}/cancel")
            ->assertOk();

        $this->assertSame(Order::CANCELLED, $this->order->fresh()->status);
    }

    /** @test */
    public function a_guest_cannot_view_an_order()
    {
        $this->getJson("/api/v1/orders/{$this->order->id}/info")->assertStatus(401);
    }

    /** @test */
    public function the_owner_can_view_their_order()
    {
        $this->actingAs($this->owner, 'api-customers')
            ->getJson("/api/v1/orders/{$this->order->id}/info")
            ->assertOk()
            ->assertJsonPath('data.id', $this->order->id);
    }

    /** @test */
    public function another_customer_cannot_view_the_order()
    {
        $this->actingAs($this->otherCustomer, 'api-customers')
            ->getJson("/api/v1/orders/{$this->order->id}/info")
            ->assertNotFound();
    }

    /** @test */
    public function the_assigned_driver_can_view_the_order()
    {
        $driver = Driver::factory()->create();
        $this->order->update(['driver_id' => $driver->id]);

        $this->actingAs($driver, 'api-drivers')
            ->getJson("/api/v1/orders/{$this->order->id}/info")
            ->assertOk();
    }

    /** @test */
    public function an_invited_driver_can_view_the_order()
    {
        $driver = Driver::factory()->create();
        OrderInvitation::create([
            'order_id' => $this->order->id,
            'driver_id' => $driver->id,
            'is_waiting_acceptation' => true,
        ]);

        $this->actingAs($driver, 'api-drivers')
            ->getJson("/api/v1/orders/{$this->order->id}/info")
            ->assertOk();
    }

    /** @test */
    public function an_unrelated_driver_cannot_view_the_order()
    {
        $driver = Driver::factory()->create();

        $this->actingAs($driver, 'api-drivers')
            ->getJson("/api/v1/orders/{$this->order->id}/info")
            ->assertNotFound();
    }
}
