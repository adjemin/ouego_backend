<?php

namespace App\Events;

use App\Models\OrderInvitation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Une invitation toujours en attente affiche des informations qui ont changé (commande ou
 * rémunération du chauffeur). Diffusé sur private-drivers.{driverId}, avec le même payload
 * que order.invitation.created : l'app remplace l'invitation dans sa liste.
 *
 * Le payload est figé à la création : il reflète l'état au moment du changement.
 */
class OrderInvitationUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public int $driverId;

    public array $payload;

    public function __construct(OrderInvitation $orderInvitation)
    {
        $this->driverId = $orderInvitation->driver_id;
        $this->payload = $orderInvitation->broadcastPayload();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('drivers.'.$this->driverId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.invitation.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
