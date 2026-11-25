<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Diffusé quand un paiement atteint un état final, pour que l'app réagisse sans interroger l'API :
 *  - private-customers.{id} pour un paiement de commande ;
 *  - private-drivers.{id} pour une recharge de portefeuille chauffeur.
 */
class PaymentStatusUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public string $channel;

    public array $payload;

    /** @param string $userTable customers|drivers */
    public function __construct(Payment $payment, string $userTable, ?int $orderId = null)
    {
        $this->channel = $userTable.'.'.$payment->user_id;
        $this->payload = [
            'payment_id' => $payment->id,
            'reference' => $payment->payment_reference,
            'status' => $payment->status,
            'invoice_id' => $payment->invoice_id,
            'order_id' => $orderId,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channel)];
    }

    public function broadcastAs(): string
    {
        return 'payment.status.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
