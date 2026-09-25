<?php

namespace App\Events;

use App\Models\Invoice;
use App\Models\OrderInvitation;
use App\Models\RoutePoint;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Un chauffeur est invité sur une commande (déclenché par les services d'assignation).
 *
 * En plus de la notification push (SendOrderAssignmentNotification), l'invitation est
 * diffusée au chauffeur sur private-drivers.{driverId} pour la liste
 * drivers/orders_invitations/list. Le payload est lu au moment de l'envoi : une invitation
 * déjà acceptée, refusée ou expirée entre-temps n'est pas diffusée.
 */
class OrderAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $orderInvitation;

    /**
     * Create a new event instance.
     */
    public function __construct(OrderInvitation $orderInvitation)
    {
        //
        $this->orderInvitation = $orderInvitation;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('drivers.'.$this->orderInvitation->driver_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.invitation.created';
    }

    public function broadcastWhen(): bool
    {
        return OrderInvitation::whereKey($this->orderInvitation->id)
            ->where('is_waiting_acceptation', true)
            ->exists();
    }

    public function broadcastWith(): array
    {
        $invitation = $this->orderInvitation->fresh();
        $order = $invitation->order;
        $invoice = $order ? Invoice::where('order_id', $order->id)->first() : null;

        return [
            'invitation_id' => $invitation->id,
            'order_id' => $invitation->order_id,
            'status' => $invitation->status,
            'created_at' => $invitation->created_at?->toIso8601String(),
            'order' => $order ? [
                'reference' => $order->reference,
                'service_slug' => $order->service_slug,
                'delivery_type_code' => $order->delivery_type_code,
                'driver_due' => $invoice?->driver_due,
                'currency_code' => $order->currency_code,
                'route_points' => RoutePoint::where('order_id', $order->id)
                    ->orderBy('visit_order')
                    ->get()
                    ->map(fn (RoutePoint $point) => $point->only(['type', 'address_name', 'latitude', 'longitude']))
                    ->all(),
            ] : null,
        ];
    }
}
