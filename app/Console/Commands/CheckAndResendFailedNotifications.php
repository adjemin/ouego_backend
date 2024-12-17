<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SendPushNotification;
use App\Models\NotificationDeliveryStatus;
use Carbon\Carbon;

class CheckAndResendFailedNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:check-failed';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and resend failed notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Récupère les notifications envoyées mais non confirmées depuis plus de 5 minutes
        $failedNotifications = NotificationDeliveryStatus::where('status', 'SENT')
        ->where('created_at', '<=', Carbon::now()->subMinutes(5))
        ->where('attempt_count', '<', 3)
        ->get();

        foreach ($failedNotifications as $notification) {
            // Incrémente le compteur de tentatives
            $notification->increment('attempt_count');

            // Relance la notification
            SendPushNotification::dispatch(
                $notification->fcm_token,
                $notification->notification
            )->delay(now()->addSeconds(30 * $notification->attempt_count));

            $this->info("Requeued notification {$notification->notification_id}");
        }
    }
}
