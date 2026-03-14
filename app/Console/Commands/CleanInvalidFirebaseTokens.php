<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CustomerDevice;
use Illuminate\Support\Facades\Log;
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
        Log::info('Début du nettoyage des tokens Firebase...');
        $this->info('Début du nettoyage des tokens Firebase...');

        $serviceAccount = base_path('storage/app/firebase/ouego-44587-firebase-adminsdk-fbsvc-e862305a22.json');
        
        $devices = CustomerDevice::all();
        $factory = (new \Kreait\Firebase\Factory)->withServiceAccount($serviceAccount);
        $messaging = $factory->createMessaging();

        // Regrouper les tokens par lots de 1000 (limite de Firebase)
        $tokenChunks = $devices->chunk(1000)->map(function ($chunk) {
            return $chunk->pluck('firebase_id')->toArray();
        });

        foreach ($tokenChunks as $tokens) {
            try {
                // Valider les tokens sans envoyer de notification
                $result = $messaging->validateRegistrationTokens($tokens);

                // Traiter les résultats
                foreach ($result['invalid'] as $invalidToken) {
                    $device = CustomerDevice::where('firebase_id', $invalidToken)->first();
                    if ($device) {
                        $this->info("Suppression du token invalide: " . $invalidToken);
                        $device->forceDelete();
                    }
                }
            } catch (MessagingException $e) {
                $this->error("Erreur lors de la validation des tokens: " . $e->getMessage());
            }
        }

        $this->info('Nettoyage des tokens terminé.');
        Log::info('Nettoyage des tokens terminé.');
    }
}
