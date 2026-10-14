<?php

namespace Tests\Feature\Assignment;

use App\Models\Carrier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Services\TripService;
use Tests\TestCase;

class TripServiceTest extends TestCase
{
    /** @test */
    public function the_driver_search_runs_without_calling_a_missing_method()
    {
        $carrier = Carrier::create([
            'name' => 'Carrière',
            'location_latitude' => 5.37,
            'location_longitude' => -4.00,
            'location' => ['latitude' => 5.37, 'longitude' => -4.00],
        ]);
        $order = Order::create([
            'reference' => Order::generateReference(),
            'status' => Order::PERFORMER_LOOKUP,
            'currency_code' => 'XOF',
            'is_draft' => false,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'service_slug' => Service::AGREGATS_CONSTRUCTION,
            'carrier_id' => $carrier->id,
        ]);

        $result = app(TripService::class)->getDriverAndNotify($order->fresh());

        $this->assertFalse($result['success']);
        $this->assertSame('Erreur lors de la recherche de chauffeurs: Aucun chauffeur trouvé à proximité de la carrière', $result['message']);
    }
}
