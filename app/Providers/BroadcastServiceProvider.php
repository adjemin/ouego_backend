<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // POST /api/v1/broadcasting/auth : les apps mobiles s'authentifient avec leur JWT client
        Broadcast::routes([
            'prefix' => 'api/v1',
            'middleware' => ['api', 'auth.customer:api-customers'],
        ]);

        require base_path('routes/channels.php');
    }
}
