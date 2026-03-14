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
            $factory = (new Factory)->withServiceAccount($jsonPath);
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
