<?php

namespace App\Providers;

use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // POST /api/v1/broadcasting/auth : app client, authentifiée avec le JWT client
        Broadcast::routes([
            'prefix' => 'api/v1',
            'middleware' => ['api', 'auth.customer:api-customers'],
        ]);

        // POST /api/v1/drivers/broadcasting/auth : app chauffeur, authentifiée avec le JWT chauffeur
        Route::post('api/v1/drivers/broadcasting/auth', [BroadcastController::class, 'authenticate'])
            ->middleware(['api', 'auth.driver:api-drivers']);

        require base_path('routes/channels.php');
    }
}
