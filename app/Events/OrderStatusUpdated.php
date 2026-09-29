<?php

namespace App\Events;

use App\Models\Driver;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Diffusé à chaque changement de statut ou de chauffeur, sur deux canaux :
 *  - private-orders.{id} : écran de suivi de la commande ;
 *  - private-customers.{customerId} : liste des commandes du client (customers/orders/list).
 *
 * Le payload est figé à la création : le broadcast passe par la queue et doit refléter
 * l'état au moment du changement, pas celui de la commande quand le worker le traite.
 */
class OrderStatusUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public int $orderId;

    public ?int $customerId;

    public array $payload;

    public function __construct(Order $order, ?string $previousStatus = null)
    {
        $this->orderId = $order->id;
        $this->customerId = $order->customer_id;

        $driver = $order->driver_id ? Driver::find($order->driver_id) : null;

        $this->payload = [
            'order_id' => $order->id,
            'reference' => $order->reference,
            'status' => $order->status,
            'service_slug' => $order->service_slug,
            'previous_status' => $previousStatus,
            'is_completed' => (bool) $order->is_completed,
            'acceptation_time' => $order->acceptation_time,
            'driver' => $driver ? [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
                'photo_url' => $driver->photo_url,
                'rate' => $driver->rate,
            ] : null,
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        return array_values(array_filter([
            new PrivateChannel('orders.'.$this->orderId),
            $this->customerId ? new PrivateChannel('customers.'.$this->customerId) : null,
        ]));
    }

    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
