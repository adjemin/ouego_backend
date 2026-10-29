<?php

namespace Tests\Feature\Orders;

use App\Jobs\SendSms;
use App\Models\Commercial;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Models\RoutePoint;
use App\Models\Service;
use App\Services\DriverAssignmentService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Orders\Aggregats\AggregatOrderTestCase;

/**
 * Parcours d'une commande après sa confirmation : acceptation par un chauffeur,
 * enlèvement à la carrière, livraison, règlement du chauffeur, puis notation.
 */
class OrderDeliveryLifecycleTest extends AggregatOrderTestCase
{
    private Driver $driver;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        $this->mock(DriverAssignmentService::class)->shouldIgnoreMissing();

        $this->driver = Driver::factory()->create(['current_balance' => 50000, 'old_balance' => 50000, 'rate' => 0]);
    }

    /** @test */
    public function a_driver_accepting_the_invitation_gets_the_order()
    {
        $this->confirmedOrder('cash');
        $invitation = $this->invite($this->driver);
        $otherInvitation = $this->invite(Driver::factory()->create());

        $this->actingAs($this->driver, 'api-drivers')
            ->putJson("/api/v1/drivers/orders_invitations/{$invitation->id}/accept")
            ->assertOk();

        $order = $this->order->fresh();
        $this->assertSame(Order::PERFORMER_FOUND, $order->status);
        $this->assertSame($this->driver->id, $order->driver_id);
        $this->assertNotNull($order->acceptation_time);

        $this->assertFalse((bool) $invitation->fresh()->is_waiting_acceptation);
        $this->assertNotNull($invitation->fresh()->acceptation_time);
        $this->assertFalse((bool) $otherInvitation->fresh()->is_waiting_acceptation);
        $this->assertNull($otherInvitation->fresh()->acceptation_time);

        $this->assertSame(
            [RoutePoint::WAITING],
            RoutePoint::where('order_id', $order->id)->distinct()->pluck('status')->all()
        );
        $this->assertDatabaseHas('order_histories', ['order_id' => $order->id, 'status' => Order::PERFORMER_FOUND]);
    }

    /** @test */
    public function an_invitation_cannot_be_accepted_twice()
    {
        $this->confirmedOrder('cash');
        $invitation = $this->invite($this->driver);

        $this->actingAs($this->driver, 'api-drivers')
            ->putJson("/api/v1/drivers/orders_invitations/{$invitation->id}/accept")
            ->assertOk();

        $this->actingAs($this->driver, 'api-drivers')
            ->putJson("/api/v1/drivers/orders_invitations/{$invitation->id}/accept")
            ->assertStatus(400);
    }

    /** @test */
    public function a_driver_cannot_accept_an_invitation_sent_to_another_driver()
    {
        $this->confirmedOrder('cash');
        $invitation = $this->invite(Driver::factory()->create());

        $this->actingAs($this->driver, 'api-drivers')
            ->putJson("/api/v1/drivers/orders_invitations/{$invitation->id}/accept")
            ->assertStatus(400);

        $this->assertNull($this->order->fresh()->driver_id);
        $this->assertTrue((bool) $invitation->fresh()->is_waiting_acceptation);
    }

    /** @test */
    public function a_cash_order_goes_through_pickup_and_delivery()
    {
        $this->acceptedOrder('cash');
        [$source, $destination] = $this->routePoints();

        $this->updateRoutePoint($source, RoutePoint::STARTED);
        $order = $this->order->fresh();
        $this->assertTrue((bool) $order->is_started);
        $this->assertTrue((bool) $order->is_running);

        $this->updateRoutePoint($source, RoutePoint::ARRIVED);
        $this->assertSame(Order::PICKUP_ARRIVED, $this->order->fresh()->status);

        $this->updateRoutePoint($source, RoutePoint::SUCCESS);
        $this->assertSame(Order::PICKUPED, $this->order->fresh()->status);
        $this->assertTrue((bool) $source->fresh()->is_successful);

        $this->updateRoutePoint($destination, RoutePoint::ARRIVED);
        $this->assertSame(Order::DELIVERED, $this->order->fresh()->status);

        // Pas de règlement avant la fin de la livraison
        $this->assertEquals(50000, $this->driver->fresh()->current_balance);

        $this->updateRoutePoint($destination, RoutePoint::SUCCESS)
            ->assertJsonPath('data.status', Order::DELIVERED_FINISH);

        $order = $this->order->fresh();
        $this->assertTrue((bool) $order->is_completed);
        $this->assertTrue((bool) $order->is_successful);
        $this->assertFalse((bool) $order->is_running);
        $this->assertNotNull($order->completion_time);

        foreach ([Order::PERFORMER_FOUND, Order::PICKUP_ARRIVED, Order::PICKUPED, Order::DELIVERED, Order::DELIVERED_FINISH] as $status) {
            $this->assertDatabaseHas('order_histories', ['order_id' => $order->id, 'status' => $status]);
        }
    }

    /** @test */
    public function a_delivered_cash_order_marks_the_invoice_paid_and_debits_the_commission()
    {
        $this->acceptedOrder('cash');
        $invoice = $this->order->invoice;
        $this->assertGreaterThan(0, $invoice->service_due);

        $this->deliver();

        $invoice->refresh();
        $this->assertSame(Invoice::PAID, $invoice->status);
        $this->assertTrue((bool) $invoice->is_paid_by_customer);
        $this->assertEquals(50000 - $invoice->service_due, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function a_delivered_online_order_credits_the_driver_due()
    {
        $this->acceptedOrder('online');
        $invoice = $this->order->invoice;
        $this->assertGreaterThan(0, $invoice->driver_due);

        $this->deliver();

        // Le paiement en ligne passe par le webhook : la livraison ne marque pas la facture payée
        $this->assertSame(Invoice::UNPAID, $invoice->fresh()->status);
        $this->assertEquals(50000 + $invoice->driver_due, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function the_commercial_is_credited_only_once_the_order_is_delivered()
    {
        Queue::fake();
        $commercial = Commercial::create(['name' => 'Commercial Test', 'phone' => '2250100000000', 'code' => 'COM1', 'current_balance' => 0, 'is_active' => true]);
        $this->customer->update(['code_commercial' => 'COM1']);

        $this->acceptedOrder('cash');
        $this->assertSame('COM1', $this->order->invoice->coupon);
        [$source, $destination] = $this->routePoints();

        foreach ([[$source, RoutePoint::STARTED], [$source, RoutePoint::ARRIVED], [$source, RoutePoint::SUCCESS],
            [$destination, RoutePoint::ARRIVED]] as [$point, $status]) {
            $this->updateRoutePoint($point, $status);
        }
        $this->assertEquals(0, $commercial->fresh()->current_balance);
        Queue::assertNotPushed(SendSms::class);

        $this->updateRoutePoint($destination, RoutePoint::SUCCESS);

        $this->assertEquals(2500, $commercial->fresh()->current_balance);
        $this->assertDatabaseHas('transactions', ['user_id' => $commercial->id, 'user_source' => 'commercials', 'order_id' => $this->order->id]);
        Queue::assertPushed(SendSms::class, fn (SendSms $job) => $job->phoneNumber === '+2250100000000'
            && str_contains($job->message, 'Votre compte a été credité de 2500 XOF'));
    }

    /** @test */
    public function the_commercial_is_not_credited_twice_for_the_same_order()
    {
        Queue::fake();
        $commercial = Commercial::create(['name' => 'Commercial Test', 'phone' => '2250100000000', 'code' => 'COM1', 'current_balance' => 0, 'is_active' => true]);
        $this->customer->update(['code_commercial' => 'COM1']);

        $this->acceptedOrder('cash');
        $this->deliver();

        [, $destination] = $this->routePoints();
        $this->updateRoutePoint($destination, RoutePoint::SUCCESS);

        $this->assertEquals(2500, $commercial->fresh()->current_balance);
        Queue::assertPushed(SendSms::class, 1);
    }

    /** @test */
    public function an_order_without_commercial_code_credits_no_commercial()
    {
        Queue::fake();
        $commercial = Commercial::create(['name' => 'Commercial Test', 'phone' => '2250100000000', 'code' => 'COM1', 'current_balance' => 0, 'is_active' => true]);

        $this->acceptedOrder('cash');
        $this->deliver();

        $this->assertEquals(0, $commercial->fresh()->current_balance);
        Queue::assertNotPushed(SendSms::class);
    }

    /** @test */
    public function a_failed_pickup_does_not_advance_the_order()
    {
        $this->acceptedOrder('cash');
        [$source] = $this->routePoints();

        $this->updateRoutePoint($source, RoutePoint::FAILED);

        $source->refresh();
        $this->assertTrue((bool) $source->is_completed);
        $this->assertFalse((bool) $source->is_successful);
        $this->assertSame(Order::PERFORMER_FOUND, $this->order->fresh()->status);
        $this->assertEquals(50000, $this->driver->fresh()->current_balance);
    }

    /** @test */
    public function a_route_point_update_rejects_an_invalid_status()
    {
        $this->acceptedOrder('cash');
        [$source] = $this->routePoints();

        $this->actingAs($this->driver, 'api-drivers')
            ->putJson("/api/v1/route_points/{$source->id}/update_status", ['status' => 'teleported'])
            ->assertStatus(400);

        $this->assertSame(RoutePoint::WAITING, $source->fresh()->status);
    }

    /** @test */
    public function another_driver_cannot_update_the_route_points_of_the_order()
    {
        $this->acceptedOrder('cash');
        [$source, $destination] = $this->routePoints();
        $intruder = Driver::factory()->create(['current_balance' => 50000, 'old_balance' => 50000]);

        foreach ([[$source, RoutePoint::ARRIVED], [$destination, RoutePoint::SUCCESS]] as [$point, $status]) {
            $this->actingAs($intruder, 'api-drivers')
                ->putJson("/api/v1/route_points/{$point->id}/update_status", ['status' => $status])
                ->assertStatus(400)
                ->assertJsonPath('message', "Point d'arrêt introuvable");

            $this->assertSame(RoutePoint::WAITING, $point->fresh()->status);
        }

        $this->assertSame(Order::PERFORMER_FOUND, $this->order->fresh()->status);
        $this->assertSame(Invoice::UNPAID, $this->order->invoice->fresh()->status);
        $this->assertEquals(50000, $this->driver->fresh()->current_balance);
        $this->assertEquals(50000, $intruder->fresh()->current_balance);
        $this->assertDatabaseCount('route_point_histories', 0);
    }

    /** @test */
    public function no_driver_can_update_the_route_points_before_the_order_is_accepted()
    {
        $this->confirmedOrder('cash');
        [$source] = $this->routePoints();

        $this->actingAs($this->driver, 'api-drivers')
            ->putJson("/api/v1/route_points/{$source->id}/update_status", ['status' => RoutePoint::STARTED])
            ->assertStatus(400);

        $this->assertFalse((bool) $this->order->fresh()->is_started);
    }

    /** @test */
    public function a_route_point_update_requires_a_status()
    {
        $this->acceptedOrder('cash');
        [$source] = $this->routePoints();

        $this->actingAs($this->driver, 'api-drivers')
            ->putJson("/api/v1/route_points/{$source->id}/update_status", [])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Le champ status est obligatoire');
    }

    /** @test */
    public function the_customer_rates_a_delivered_order_and_the_driver_average_is_updated()
    {
        $this->acceptedOrder('cash');
        $this->deliver();

        Order::create([
            'reference' => Order::generateReference(),
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'status' => Order::DELIVERED_FINISH,
            'currency_code' => 'XOF',
            'rating' => 3,
        ]);

        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$this->order->id}/rating", ['rating' => 5, 'note' => 'Parfait'])
            ->assertOk()
            ->assertJsonPath('data.rating', 5);

        $this->assertSame('Parfait', $this->order->fresh()->rating_note);
        $this->assertEquals(4, $this->driver->fresh()->rate);
    }

    /**
     * @test
     * @dataProvider invalidRatings
     */
    public function an_invalid_rating_is_refused($rating)
    {
        $this->acceptedOrder('cash');

        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$this->order->id}/rating", ['rating' => $rating])
            ->assertStatus(400);

        $this->assertNull($this->order->fresh()->rating);
    }

    public static function invalidRatings(): array
    {
        return ['zéro' => [0], 'six' => [6], 'texte' => ['excellent']];
    }

    /** @test */
    public function the_customer_sees_the_order_history()
    {
        $this->acceptedOrder('cash');

        $statuses = collect(
            $this->actingAs($this->customer, 'api-customers')
                ->getJson("/api/v1/orders/{$this->order->id}/history")
                ->assertOk()
                ->json('data')
        )->pluck('status');

        $this->assertEquals([Order::INITIATED, Order::PERFORMER_LOOKUP, Order::PERFORMER_FOUND], $statuses->all());
    }

    private function confirmedOrder(string $paymentMethod): void
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

        $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/orders/{$orderId}/confirm")
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->order = Order::findOrFail($orderId);
    }

    private function acceptedOrder(string $paymentMethod): void
    {
        $this->confirmedOrder($paymentMethod);
        $invitation = $this->invite($this->driver);

        $this->actingAs($this->driver, 'api-drivers')
            ->putJson("/api/v1/drivers/orders_invitations/{$invitation->id}/accept")
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->order->refresh();
    }

    private function invite(Driver $driver): OrderInvitation
    {
        return OrderInvitation::create([
            'order_id' => $this->order->id,
            'driver_id' => $driver->id,
            'is_waiting_acceptation' => true,
        ]);
    }

    /** @return RoutePoint[] [source, destination] */
    private function routePoints(): array
    {
        return [
            RoutePoint::where(['order_id' => $this->order->id, 'type' => RoutePoint::POINT_TYPE_SOURCE])->sole(),
            RoutePoint::where(['order_id' => $this->order->id, 'type' => RoutePoint::POINT_TYPE_DESTINATION])->sole(),
        ];
    }

    private function updateRoutePoint(RoutePoint $routePoint, string $status)
    {
        $response = $this->actingAs($this->driver, 'api-drivers')
            ->putJson("/api/v1/route_points/{$routePoint->id}/update_status", [
                'status' => $status,
                'latitude' => $routePoint->latitude,
                'longitude' => $routePoint->longitude,
            ])
            ->assertOk();

        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function deliver(): void
    {
        [$source, $destination] = $this->routePoints();

        foreach ([[$source, RoutePoint::STARTED], [$source, RoutePoint::ARRIVED], [$source, RoutePoint::SUCCESS],
            [$destination, RoutePoint::ARRIVED], [$destination, RoutePoint::SUCCESS]] as [$point, $status]) {
            $this->updateRoutePoint($point, $status);
        }
    }
}
