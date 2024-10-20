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

                    try {
                        // Tentative d'envoi de la notification
                        SendPushCustomerNotification::dispatch($userDevice->firebase_id, $event->customerNotification);
                    } catch (Kreait\Firebase\Exception\Messaging\NotFound $e) {
                        // Le token n'est plus valide, nous le supprimons
                        $userDevice->delete();
                        \Log::warning("Token Firebase invalide supprimé pour l'utilisateur " . $userDevice->customer_id);
                    } catch (\Exception $e) {
                        // Gestion des autres exceptions
                        \Log::error("Erreur lors de l'envoi de la notification: " . $e->getMessage());
                    }

                }

            }

    }
}
