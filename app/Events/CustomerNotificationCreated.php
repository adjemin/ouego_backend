<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\CustomerNotification;

class CustomerNotificationCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $customerNotification;

    /**
     * Crée une nouvelle instance de l'événement.
     *
     * @param  CustomerNotification  $customerNotification
     * @return void
     */
    public function __construct(CustomerNotification $customerNotification)
    {
        $this->customerNotification = $customerNotification;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
