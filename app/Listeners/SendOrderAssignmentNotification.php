<?php

namespace App\Listeners;

use App\Events\OrderAssigned;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\DriverNotification;
use App\Models\DriverDevice;
use App\Models\NotificationDeliveryStatus;
use App\Jobs\SendPushNotification;
use App\Utilities\TwilioUtils;
use Illuminate\Support\Facades\Log;

class SendOrderAssignmentNotification implements ShouldQueue
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

        $driver = \App\Models\Driver::find($event->orderInvitation->driver_id);
        $client_phone = $driver->phone;
        //$message = "Course #".$event->orderInvitation->order_id." vous a été affectée | +". $client_phone." | Acceptez ou Refusez la course";
        //TwilioUtils::sendSMS($client_phone, $message);

        Log::info("SendOrderAssignmentNotification started");
        echo "SendOrderAssignmentNotification started. Driver id: " . strval($driver->id) . PHP_EOL;

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

            try {
                // Tentative d'envoi de la notification
                SendPushNotification::dispatch($device->firebase_id, $notification);
            } catch (Kreait\Firebase\Exception\Messaging\NotFound $e) {
                // Le token n'est plus valide, nous le supprimons
                $device->forceDelete();
                \Log::warning("Token Firebase invalide supprimé pour l'utilisateur " . $event->orderInvitation->driver_id);
            } catch (\Exception $e) {
                // Gestion des autres exceptions
                \Log::error("Erreur lors de l'envoi de la notification: " . $e->getMessage());
            }
        }



    }
}
