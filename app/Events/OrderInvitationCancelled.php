<?php

namespace App\Events;

use App\Models\OrderInvitation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Une invitation en attente n'est plus disponible pour le chauffeur : l'app la retire
 * de drivers/orders_invitations/list. Diffusé sur private-drivers.{driverId}.
 *
 * Le payload est figé à la création : l'invitation peut être supprimée avant que le
 * worker ne traite le broadcast.
 */
class OrderInvitationCancelled implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    const ORDER_CANCELLED = 'order_cancelled';
    const TAKEN_BY_ANOTHER_DRIVER = 'taken_by_another_driver';
    const EXPIRED = 'expired';
    const REASSIGNED = 'reassigned';

    public int $driverId;

    public array $payload;

    public function __construct(OrderInvitation $orderInvitation, string $reason)
    {
        $this->driverId = $orderInvitation->driver_id;

        $this->payload = [
            'invitation_id' => $orderInvitation->id,
            'order_id' => $orderInvitation->order_id,
            'reason' => $reason,
            'cancelled_at' => now()->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('drivers.'.$this->driverId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.invitation.cancelled';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
