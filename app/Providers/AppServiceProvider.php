<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        Queue::failing(function (JobFailed $event) {
            // Notification ou logging personnalisé
            \Log::error('Job failed', [
                'job' => get_class($event->job),
                'exception' => $event->exception->getMessage(),
            ]);
        });
    }
}
