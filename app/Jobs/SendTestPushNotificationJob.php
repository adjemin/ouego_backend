<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\DriverDevice;
use App\Models\NotificationDeliveryStatus;
use App\Utilities\FirebaseMessagingUtils;
use Kreait\Firebase\Factory;

class SendTestPushNotificationJob
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    // Nombre maximum de tentatives
    public $tries = 3;

    // Timeout en secondes
    public $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
        try {
            // Récupérer tous les appareils des chauffeurs
            $driverDevices = DriverDevice::whereNotNull('firebase_id')->get();

            // Données de la notification de test
            $notification = [
                'title' => 'Test Notification Ouego Pro',
                'body' => 'Ceci est une notification de test. ' . now()->format('H:i:s'),
                'type' => 'test',
                'id' => uniqid('notification_')
            ];

            foreach ($driverDevices as $device) {

                $serviceAccount =  config('firebase.ouego.pro');

                $factory = (new Factory)
                ->withServiceAccount($serviceAccount);

            $messaging = $factory->createMessaging();

            // Ajout d'un messageId unique pour le suivi
            $messageId = uniqid('msg_');

            $message = FirebaseMessagingUtils::buildMessage(
                $device->firebase_id,
                $notification['title'],
                $notification['body'],
                $notification['type'],
                $notification['id'],
                $notification['id'],
                ['message_id' => $messageId]
            );

            $result = $messaging->send($message);

                // Enregistrement du statut de livraison
                $deliveryStatus = new NotificationDeliveryStatus([
                    'notification_id' => null, // Si besoin de lier à une notification
                    'fcm_token' => $device->firebase_id,
                    'fcm_message_id' => $messageId,
                    'attempt_count' => 1,
                    'status' => 'PENDING',
                    'error_message' => null,
                    'delivered_at' => null
                ]);

                $deliveryStatus->save();

            }
        } catch (\Exception $e) {

            throw $e; // Relance l'exception pour que le job soit réessayé
        }
    }
}
