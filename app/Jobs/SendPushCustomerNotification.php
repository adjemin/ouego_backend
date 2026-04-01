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
        try {
            $serviceAccount = config('firebase.ouego.dev');
            $factory = (new Factory)->withServiceAccount($serviceAccount);
            $messaging = $factory->createMessaging();

            $message = CloudMessage::withTarget('token', $this->firebaseId)
                ->withData([
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'id' => (string)$this->customerNotification->id,
                    'status' => 'done',
                    'notification_type' => (string)$this->customerNotification->type,
                    'notification_id' => (string)$this->customerNotification->id,
                    'meta_data_id' => (string)$this->customerNotification->data_id,
                    "title" => $this->customerNotification->title,
                    "body" => $this->customerNotification->subtitle
                ]);

            $result = $messaging->send($message);
        } catch (Kreait\Firebase\Exception\Messaging\NotFound $e) {
            // Le token n'est plus valide            throw $e; // Relancer l'exception pour que le job échoue et soit potentiellement retryé
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
