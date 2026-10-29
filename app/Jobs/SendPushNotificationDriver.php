<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\DriverNotification;
use App\Models\NotificationDeliveryStatus;
use App\Utilities\FirebaseMessagingUtils;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Log;


class SendPushNotificationDriver
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    // Nombre maximum de tentatives
    public $tries = 3;

    // Délai entre les tentatives (en secondes)
    public $backoff = [10, 60, 180];


    protected $fcmToken;
    protected $notificationData;
    /**
     * Create a new job instance.
     */
    public function __construct(string $fcmToken, DriverNotification $notificationData)
    {
        //
        $this->fcmToken = $fcmToken;
        $this->notificationData = $notificationData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Création du statut de livraison
        $deliveryStatus = NotificationDeliveryStatus::create([
            'notification_id' => $this->notificationData->id,
            'fcm_token' => $this->fcmToken,
            'attempt_count' => 1,
            'status' => 'PENDING'
        ]);

        try {
            $serviceAccount = config('firebase.ouego.pro');
            $factory = (new Factory)
                ->withServiceAccount($serviceAccount);

            $messaging = $factory->createMessaging();

            // Ajout d'un messageId unique pour le suivi
            $messageId = uniqid('msg_');

            $message = FirebaseMessagingUtils::buildMessage(
                $this->fcmToken,
                $this->notificationData->title,
                $this->notificationData->subtitle,
                $this->notificationData->type,
                $this->notificationData->id,
                $this->notificationData->data_id,
                ['message_id' => $messageId]
            );

            $result = $messaging->send($message);

           // Mise à jour du statut avec l'ID du message FCM
           $deliveryStatus->update([
                'fcm_message_id' => $messageId,
                'status' => 'SENT'
            ]);


        } catch (\Exception $e) {

            $deliveryStatus->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage()
            ]);

            // Relance le job si des tentatives sont encore disponibles
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1]);
            }

            throw $e;
        }
    }
}
