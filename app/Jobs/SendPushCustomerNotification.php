<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\CustomerNotification;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Log;

class SendPushCustomerNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $customerNotification;

    protected $firebaseId;

    /**
     * Crée une nouvelle instance du job.
     *
     * @param  string  $firebaseId
     * @param  CustomerNotification  $customerNotification
     * @return void
     */
    public function __construct($firebaseId, CustomerNotification $customerNotification)
    {
        $this->customerNotification = $customerNotification;
        $this->firebaseId = $firebaseId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
        $jsonPath = base_path('ouego-dev-firebase-adminsdk-9z99b-48b56e20fd.json');

            $factory = (new Factory)
             ->withServiceAccount($jsonPath);

        $messaging = $factory->createMessaging();

        $message = CloudMessage::withTarget('token', $this->firebaseId)
            ->withNotification(Notification::create($this->customerNotification->title, $this->customerNotification->subtitle))
            ->withData(array(
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'id' => "".$this->customerNotification->id,
                'status' => 'done',
                'notification_type' => "".$this->customerNotification->type,
                'notification_id' => "".$this->customerNotification->id,
                'meta_data_id' => "".$this->customerNotification->data_id,
                "title" => "".$this->customerNotification->title,
                "body" => "".$this->customerNotification->subtitle
            ));

        $result = $messaging->send($message);

        Log::info("Result =>> ".json_encode($result));
    }
}
