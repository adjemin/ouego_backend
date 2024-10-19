<?php

namespace App\Listeners;

use App\Events\CustomerNotificationCreated;
use App\Jobs\SendPushCustomerNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\CustomerDevice;

class SendCustomerPushNotification implements ShouldQueue
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
    public function handle(CustomerNotificationCreated $event): void
    {
            $userDevices = CustomerDevice::where([
                "customer_id" => $event->customerNotification->customer_id,
                "deleted_at" => null
            ])->orderBy('updated_at', 'DESC')->get();

            if($userDevices != null){

                foreach($userDevices as $userDevice){
                     // Dispatch le job pour envoyer la notification push
                    SendPushCustomerNotification::dispatch($userDevice->firebase_id, $event->customerNotification);
                }

            }

    }
}
