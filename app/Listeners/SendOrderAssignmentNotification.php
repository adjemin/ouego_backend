<?php

namespace App\Listeners;

use App\Events\OrderAssigned;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\DriverNotification;
use App\Models\DriverDevice;
use App\Jobs\SendPushNotification;

class SendOrderAssignmentNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OrderAssigned $event): void
    {

        //Push Notification
        $notification = DriverNotification::create([
            'driver_id' => $event->orderInvitation->driver_id,
            'title' => 'Course #'.$event->orderInvitation->order_id." vous a été affectée",
            'subtitle' => "Acceptez ou Refusez la course",
            'data_id' => $event->orderInvitation->id,
            'type' => $event->orderInvitation->table,
            'is_read' => false,
            'is_received' => false,
            'meta_data' => null
        ]);

        $devices = DriverDevice::where('driver_id', $event->orderInvitation->driver_id)->get();

        foreach($devices as $device){
            SendPushNotification::dispatch($device->firebase_id, $notification);
        }



    }
}
