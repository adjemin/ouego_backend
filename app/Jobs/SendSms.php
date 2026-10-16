<?php

namespace App\Jobs;

use App\Services\OrangeSMSService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Envoie un SMS hors de la requête HTTP : l'API Orange peut mettre jusqu'à 30 s à répondre.
 * OrangeSMSService::sendSMS journalise ses erreurs sans les lever, d'où une seule tentative.
 */
class SendSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 1;

    public function __construct(
        public string $phoneNumber,
        public string $message,
    ) {
    }

    public function handle(OrangeSMSService $orangeSMSService): void
    {
        $orangeSMSService->sendSMS($this->phoneNumber, $this->message);
    }
}
