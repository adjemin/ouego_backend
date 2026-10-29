<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\CustomerNotification;
use App\Utilities\FirebaseMessagingUtils;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Log;

class SendPushCustomerNotification
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

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
        // Les exceptions (dont NotFound pour un token invalide) remontent au listener
        // SendCustomerPushNotification, qui supprime l'appareil concerné.
        $serviceAccount = config('firebase.ouego.dev');
        $factory = (new Factory)->withServiceAccount($serviceAccount);
        $messaging = $factory->createMessaging();

        $message = FirebaseMessagingUtils::buildMessage(
            $this->firebaseId,
            $this->customerNotification->title,
            $this->customerNotification->subtitle,
            $this->customerNotification->type,
            $this->customerNotification->id,
            $this->customerNotification->data_id
        );

        $messaging->send($message);
    }
}
