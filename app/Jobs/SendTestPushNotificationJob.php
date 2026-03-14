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
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
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
                'sound' => 'default',
                'badge' => '1',
                'type' => 'test',
                'id' => uniqid('notification_')
            ];

            foreach ($driverDevices as $device) {

                $jsonPath = [
                'type'                        => env('FIREBASE_TYPE', 'service_account'),
                'project_id'                  => env('FIREBASE_PROJECT_ID'),
                'private_key_id'              => env('FIREBASE_PRIVATE_KEY_ID'),
                'private_key'                 => str_replace('\\n', "\n", env('FIREBASE_PRIVATE_KEY')),
                'client_email'                => env('FIREBASE_CLIENT_EMAIL'),
                'client_id'                   => env('FIREBASE_CLIENT_ID'),
                'auth_uri'                    => env('FIREBASE_AUTH_URI'),
                'token_uri'                   => env('FIREBASE_TOKEN_URI'),
                'auth_provider_x509_cert_url' => env('FIREBASE_AUTH_PROVIDER_CERT_URL'),
                'client_x509_cert_url'        => env('FIREBASE_CLIENT_CERT_URL'),
                'universe_domain'             => env('FIREBASE_UNIVERSE_DOMAIN', 'googleapis.com'),
            ];

                $factory = (new Factory)
                ->withServiceAccount($jsonPath);

            $messaging = $factory->createMessaging();

            // Ajout d'un messageId unique pour le suivi
            $messageId = uniqid('msg_');

            $message = CloudMessage::withTarget('token', $device->firebase_id)
             ->withNotification(Notification::fromArray($notification));
             /*->withData(array(
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'id' => "".$notification['id'],
                    'status' => 'done',
                    'notification_type' => "".$notification['type'],
                    'notification_id' => "".$notification['id'],
                    'meta_data_id' => "".$notification['id'],
                    //'notification' => json_encode($customerNotification),
                    "title" => "".$notification['title'],
                    "body" => "".$notification['body'],
                    'message_id' => $messageId
                ));*/

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
