<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\ProcessPendingOrderAssignments;
use App\Jobs\SendTestPushNotificationJob;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();

        $schedule->command('firebase:clean-tokens')->daily();

        // Exécuter le job toutes les 2 minutes
        $schedule->job(new ProcessPendingOrderAssignments)->everyTwoMinutes();

        $schedule->command('notifications:check-failed')->everyFiveMinutes();

        // Planification du job toutes les 2 minutes
        $schedule->job(new SendTestPushNotificationJob())
                ->everyTwoMinutes()
                ->withoutOverlapping() // Évite les chevauchements d'exécution
                ->onFailure(function () {
                    Log::error('Échec de l\'envoi des notifications push de test');
                });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
