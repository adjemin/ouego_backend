<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\DriverNotification;
use App\Models\NotificationDeliveryStatus;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
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
        try {
            // Création du statut de livraison
            $deliveryStatus = NotificationDeliveryStatus::create([
                'notification_id' => $this->notificationData->id,
                'fcm_token' => $this->fcmToken,
                'attempt_count' => 1,
                'status' => 'PENDING'
            ]);

            $serviceAccount = config('firebase.ouego.pro');
            $factory = (new Factory)
                ->withServiceAccount($serviceAccount);

            $messaging = $factory->createMessaging();

            // Ajout d'un messageId unique pour le suivi
            $messageId = uniqid('msg_');

            $notification = [
                'title' => $this->notificationData->title,
                'body' => $this->notificationData->subtitle,
                'sound' => 'notification_sound',
                'badge' => '1',
                'type' => $this->notificationData->type,
                'id' => $this->notificationData->id
            ];

            $message = CloudMessage::withTarget('token', $this->fcmToken)
               ->withNotification(Notification::fromArray($notification))
               ->withHighestPossiblePriority()
               ->withData(array(
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'id' => "".$this->notificationData->id,
                    'status' => 'done',
                    'notification_type' => "".$this->notificationData->type,
                    'notification_id' => "".$this->notificationData->id,
                    'meta_data_id' => "".$this->notificationData->data_id,
                    //'notification' => json_encode($customerNotification),
                    "title" => "".$this->notificationData->title,
                    "body" => "".$this->notificationData->subtitle,
                    'message_id' => $messageId
                ));

            $result = $messaging->send($message);

           // Mise à jour du statut avec l'ID du message FCM
           $deliveryStatus->update([
                'fcm_message_id' => $messageId,
                'status' => 'SENT'
            ]);


        } catch (Exception $e) {

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
