<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\DeliveryType;
use App\Models\Service;
use Carbon\Carbon;

class OrderPerformerLookupTest extends TestCase
{
    // =========================================================================
    // FENÊTRE DE NUIT
    // =========================================================================

    /** @test */
    public function night_lookup_window_runs_from_20h_to_07h()
    {
        $this->assertTrue(Order::isNightLookupWindow(Carbon::parse('2026-10-02 20:00')));
        $this->assertTrue(Order::isNightLookupWindow(Carbon::parse('2026-10-02 23:59')));
        $this->assertTrue(Order::isNightLookupWindow(Carbon::parse('2026-10-03 06:59')));
        $this->assertFalse(Order::isNightLookupWindow(Carbon::parse('2026-10-03 07:00')));
        $this->assertFalse(Order::isNightLookupWindow(Carbon::parse('2026-10-02 19:59')));
    }

    // =========================================================================
    // COMMANDES DE NUIT
    // =========================================================================

    /** @test */
    public function night_order_placed_during_the_day_waits_until_20h()
    {
        $order = $this->makeOrder(DeliveryType::TYPE_DE_NUIT, '2026-10-02 10:00');

        $this->assertEquals('2026-10-02 20:00:00', $order->performerLookupStartsAt()->toDateTimeString());
    }

    /** @test */
    public function night_order_placed_during_the_day_closes_at_07h_the_next_morning()
    {
        $order = $this->makeOrder(DeliveryType::TYPE_DE_NUIT, '2026-10-02 10:00');

        $this->assertEquals('2026-10-03 07:00:00', $order->performerLookupDeadline()->toDateTimeString());
    }

    /** @test */
    public function night_order_placed_after_midnight_closes_at_07h_the_same_morning()
    {
        $order = $this->makeOrder(DeliveryType::TYPE_DE_NUIT, '2026-10-03 01:30');

        $this->assertEquals('2026-10-03 01:30:00', $order->performerLookupStartsAt()->toDateTimeString());
        $this->assertEquals('2026-10-03 07:00:00', $order->performerLookupDeadline()->toDateTimeString());
    }

    // =========================================================================
    // LOCATIONS
    // =========================================================================

    /** @test */
    public function location_booked_in_advance_closes_at_the_start_date()
    {
        $order = $this->makeLocationOrder('2026-10-02 10:00', '2026-10-05');

        $this->assertEquals('2026-10-02 10:00:00', $order->performerLookupStartsAt()->toDateTimeString());
        $this->assertEquals('2026-10-05 00:00:00', $order->performerLookupDeadline()->toDateTimeString());
    }

    /** @test */
    public function location_starting_today_keeps_the_standard_timeout()
    {
        $order = $this->makeLocationOrder('2026-10-02 10:00', '2026-10-02');

        $this->assertEquals('2026-10-02 10:05:00', $order->performerLookupDeadline()->toDateTimeString());
    }

    /** @test */
    public function location_with_night_delivery_type_follows_location_rules()
    {
        $order = $this->makeLocationOrder('2026-10-02 10:00', '2026-10-05', DeliveryType::TYPE_DE_NUIT);

        $this->assertEquals('2026-10-02 10:00:00', $order->performerLookupStartsAt()->toDateTimeString());
        $this->assertEquals('2026-10-05 00:00:00', $order->performerLookupDeadline()->toDateTimeString());
    }

    // =========================================================================
    // AUTRES TYPES
    // =========================================================================

    /** @test */
    public function other_orders_close_after_the_standard_timeout()
    {
        foreach ([DeliveryType::TYPE_EXPRESS, DeliveryType::TYPE_EN_JOURNEE, DeliveryType::TYPE_DE_SEMAINE] as $type) {
            $order = $this->makeOrder($type, '2026-10-02 10:00');

            $this->assertEquals('2026-10-02 10:00:00', $order->performerLookupStartsAt()->toDateTimeString(), $type);
            $this->assertEquals('2026-10-02 10:05:00', $order->performerLookupDeadline()->toDateTimeString(), $type);
        }
    }

    // =========================================================================
    // POINT DE DÉPART : CONFIRMATION
    // =========================================================================

    /** @test */
    public function timeout_is_counted_from_confirmation_not_creation()
    {
        $order = $this->makeOrder(DeliveryType::TYPE_EXPRESS, '2026-10-02 10:00', '2026-10-02 10:12');

        $this->assertEquals('2026-10-02 10:12:00', $order->performerLookupStartsAt()->toDateTimeString());
        $this->assertEquals('2026-10-02 10:17:00', $order->performerLookupDeadline()->toDateTimeString());
    }

    /** @test */
    public function night_order_confirmed_at_night_starts_at_confirmation()
    {
        $order = $this->makeOrder(DeliveryType::TYPE_DE_NUIT, '2026-10-02 19:00', '2026-10-02 21:30');

        $this->assertEquals('2026-10-02 21:30:00', $order->performerLookupStartsAt()->toDateTimeString());
        $this->assertEquals('2026-10-03 07:00:00', $order->performerLookupDeadline()->toDateTimeString());
    }

    /** @test */
    public function location_short_notice_timeout_is_counted_from_confirmation()
    {
        $order = $this->makeLocationOrder('2026-10-02 10:00', '2026-10-02', null, '2026-10-02 10:20');

        $this->assertEquals('2026-10-02 10:25:00', $order->performerLookupDeadline()->toDateTimeString());
    }

    /** @test */
    public function falls_back_to_creation_when_order_date_is_missing()
    {
        $order = $this->makeOrder(DeliveryType::TYPE_EXPRESS, '2026-10-02 10:00');

        $this->assertEquals('2026-10-02 10:00:00', $order->confirmedAt()->toDateTimeString());
    }

    private function makeOrder(string $deliveryType, string $createdAt, ?string $orderDate = null): Order
    {
        return (new Order())->forceFill([
            'is_location'        => false,
            'delivery_type_code' => $deliveryType,
            'created_at'         => $createdAt,
            'order_date'         => $orderDate,
        ]);
    }

    private function makeLocationOrder(string $createdAt, string $startDate, ?string $deliveryType = null, ?string $orderDate = null): Order
    {
        $order = (new Order())->forceFill([
            'is_location'        => true,
            'delivery_type_code' => $deliveryType,
            'created_at'         => $createdAt,
            'order_date'         => $orderDate,
        ]);

        $order->setRelation('orderItems', collect([
            new OrderItem([
                'service_slug'        => Service::LOCATION,
                'location_start_date' => $startDate,
                'location_end_date'   => Carbon::parse($startDate)->addDays(3)->toDateString(),
            ]),
        ]));

        return $order;
    }
}
