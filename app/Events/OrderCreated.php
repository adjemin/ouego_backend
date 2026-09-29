<?php

namespace App\Events;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Diffusé au client sur private-customers.{id} quand une de ses commandes est créée.
 *
 * Contrairement à OrderStatusUpdated, le payload est lu au moment de l'envoi : à la création,
 * OrderAPIController::store() n'a pas encore calculé les montants (items et facture viennent
 * ensuite dans la même transaction). L'event n'étant envoyé qu'après le commit, la commande
 * est alors complète.
 */
class OrderCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public int $orderId;

    public int $customerId;

    public function __construct(Order $order)
    {
        $this->orderId = $order->id;
        $this->customerId = $order->customer_id;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('customers.'.$this->customerId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.created';
    }

    /**
     * La commande a pu être supprimée entre le commit et le traitement par la queue.
     */
    public function broadcastWhen(): bool
    {
        return Order::whereKey($this->orderId)->exists();
    }

    public function broadcastWith(): array
    {
        $order = Order::findOrFail($this->orderId);
        $invoice = Invoice::where('order_id', $order->id)->first();

        return [
            'order_id' => $order->id,
            'reference' => $order->reference,
            'status' => $order->status,
            'service_slug' => $order->service_slug,
            'delivery_type_code' => $order->delivery_type_code,
            'order_price' => $order->order_price,
            'delivery_price' => $order->delivery_price,
            'total' => $invoice?->total,
            'currency_code' => $order->currency_code,
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
