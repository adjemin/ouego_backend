<?php

namespace Tests\Feature\Orders;

use App\Models\Customer;
use App\Models\CustomerNotification;
use App\Models\DeliveryType;
use App\Models\Driver;
use App\Models\DriverNotification;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Models\Service;
use App\Models\Setting;
use App\Services\DriverAssignmentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ReleaseUnstartedExpressOrdersTest extends TestCase
{
    private Customer $customer;

    private Driver $driver;

    /** @var \Mockery\MockInterface */
    private $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        Queue::fake();
        Carbon::setTestNow('2026-09-22 10:00:00');

        $this->customer = Customer::create(['name' => 'Client', 'phone' => '2250700000013', 'is_active' => true]);
        $this->driver = Driver::factory()->create();

        // La recherche de chauffeurs est testée ailleurs : on vérifie seulement qu'elle est relancée
        $this->assignment = Mockery::mock(DriverAssignmentService::class);
        $this->app->instance(DriverAssignmentService::class, $this->assignment);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function acceptedOrder(int $minutesAgo, array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'reference' => Order::generateReference(),
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'service_slug' => Service::COURSE,
            'delivery_type_code' => DeliveryType::TYPE_EXPRESS,
            'status' => Order::PERFORMER_FOUND,
            'acceptation_time' => now()->subMinutes($minutesAgo),
            'currency_code' => 'XOF',
            'is_draft' => false,
            'is_completed' => false,
            'is_started' => false,
            'is_running' => false,
            'is_location' => false,
        ], $attributes));

        OrderInvitation::create([
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
            'is_waiting_acceptation' => false,
            'acceptation_time' => now()->subMinutes($minutesAgo),
        ]);

        return $order;
    }

    private function run_command(): void
    {
        $this->artisan('orders:release-unstarted-express')->assertSuccessful();
    }

    private function assertReleased(Order $order): void
    {
        $order->refresh();
        $this->assertNull($order->driver_id);
        $this->assertSame(Order::PERFORMER_LOOKUP, $order->status);
        $this->assertNull($order->acceptation_time);
    }

    private function assertKept(Order $order, string $status = Order::PERFORMER_FOUND): void
    {
        $order->refresh();
        $this->assertSame($this->driver->id, $order->driver_id);
        $this->assertSame($status, $order->status);
    }

    /** @test */
    public function an_express_order_not_started_after_the_default_15_minutes_is_released_and_reassigned()
    {
        $late = $this->acceptedOrder(16);
        $recent = $this->acceptedOrder(14);

        $this->assignment->shouldReceive('sendInvitations')
            ->once()
            ->withArgs(fn ($order) => $order->id === $late->id);

        $this->run_command();

        $this->assertReleased($late);
        $this->assertKept($recent);
    }

    /** @test */
    public function the_timeout_is_read_from_the_settings()
    {
        Setting::create(['name' => 'EXPRESS_START_TIMEOUT_MINUTES', 'value' => '5']);
        Setting::flushCache();

        $late = $this->acceptedOrder(6);
        $recent = $this->acceptedOrder(4);

        $this->assignment->shouldReceive('sendInvitations')->once();

        $this->run_command();

        $this->assertReleased($late);
        $this->assertKept($recent);
    }

    /** @test */
    public function an_invalid_setting_falls_back_to_15_minutes()
    {
        Setting::create(['name' => 'EXPRESS_START_TIMEOUT_MINUTES', 'value' => 'abc']);
        Setting::flushCache();

        $order = $this->acceptedOrder(10);
        $this->assignment->shouldNotReceive('sendInvitations');

        $this->run_command();

        $this->assertKept($order);
    }

    /** @test */
    public function started_running_or_finished_orders_are_not_touched()
    {
        $started = $this->acceptedOrder(30, ['is_started' => true]);
        $running = $this->acceptedOrder(30, ['is_running' => true]);
        $arrived = $this->acceptedOrder(30, ['status' => Order::PICKUP_ARRIVED]);
        $completed = $this->acceptedOrder(30, ['is_completed' => true]);

        $this->assignment->shouldNotReceive('sendInvitations');

        $this->run_command();

        $this->assertKept($started);
        $this->assertKept($running);
        $this->assertKept($arrived, Order::PICKUP_ARRIVED);
        $this->assertKept($completed);
    }

    /** @test */
    public function only_express_orders_are_concerned()
    {
        $day = $this->acceptedOrder(30, ['delivery_type_code' => DeliveryType::TYPE_EN_JOURNEE]);
        $night = $this->acceptedOrder(30, ['delivery_type_code' => DeliveryType::TYPE_DE_NUIT]);
        $rental = $this->acceptedOrder(30, ['is_location' => true]);

        $this->assignment->shouldNotReceive('sendInvitations');

        $this->run_command();

        $this->assertKept($day);
        $this->assertKept($night);
        $this->assertKept($rental);
    }

    /** @test */
    public function express_aggregat_orders_are_also_released()
    {
        $order = $this->acceptedOrder(20, ['service_slug' => Service::AGREGATS_CONSTRUCTION]);
        $this->assignment->shouldReceive('sendInvitations')->once();

        $this->run_command();

        $this->assertReleased($order);
    }

    /** @test */
    public function the_released_driver_keeps_a_closed_invitation_so_he_is_not_invited_again()
    {
        $order = $this->acceptedOrder(20);
        $this->assignment->shouldReceive('sendInvitations')->once();

        $this->run_command();

        $this->assertSame(0, Driver::whereKey($this->driver->id)->withoutClosedInvitationFor($order->id)->count());
    }

    /** @test */
    public function the_driver_and_the_customer_are_notified_and_the_history_is_recorded()
    {
        $order = $this->acceptedOrder(20);
        $this->assignment->shouldReceive('sendInvitations')->once();

        $this->run_command();

        $this->assertTrue(DriverNotification::where('driver_id', $this->driver->id)->where('data_id', $order->id)->exists());
        $this->assertTrue(CustomerNotification::where('customer_id', $this->customer->id)->where('data_id', $order->id)->exists());
        $this->assertDatabaseHas('order_histories', ['order_id' => $order->id, 'status' => Order::PERFORMER_LOOKUP, 'creator' => 'system']);
    }
}
