<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\DriverNotification;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        //

        $messaging = app('firebase.messaging');

        $message = CloudMessage::withTarget('token', $this->fcmToken)
            ->withNotification(Notification::create($this->notificationData->title, $this->notificationData->subtitle))
            ->withData(array(
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'id' => "".$this->notificationData->id,
                'status' => 'done',
                'notification_type' => "".$this->notificationData->type,
                'notification_id' => "".$this->notificationData->id,
                'meta_data_id' => "".$this->notificationData->data_id,
                //'notification' => json_encode($customerNotification),
                "title" => "".$this->notificationData->title,
                "body" => "".$this->notificationData->subtitle
            ));

        $messaging->send($message);
    }
}
