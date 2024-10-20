<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CustomerDevice;
use Kreait\Firebase\Messaging\CloudMessage;

class CleanInvalidFirebaseTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:clean-tokens';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean invalid Firebase tokens';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $devices = CustomerDevice::all();
        $factory = (new \Kreait\Firebase\Factory)->withServiceAccount(base_path('ouego-dev-firebase-adminsdk-9z99b-48b56e20fd.json'));
        $messaging = $factory->createMessaging();

        foreach ($devices as $device) {
            try {
                $messaging->send(
                    CloudMessage::withTarget('token', $device->firebase_id)
                        ->withNotification(['title' => 'Test', 'body' => 'Test'])
                );
            } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
                $this->info("Suppression du token invalide: " . $device->firebase_id);
                $device->delete();
            }
        }

        $this->info('Nettoyage des tokens terminé.');
    }
}
