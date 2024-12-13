<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;
use App\Services\DriverAssignmentService;
use App\Events\OrderAssigned;
use Illuminate\Support\Facades\Log;

class ProcessPendingOrderAssignments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Nombre maximum de tentatives de notification
    public $tries = 5;

    // Délai entre les tentatives (en secondes) - commence à 30s
    public $backoff = [30, 60, 120, 300, 600];

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    public function handle(DriverAssignmentService $driverAssignmentService)
    {
        // Récupérer toutes les commandes en attente d'assignation
        $pendingOrders = Order::where('status', Order::PERFORMER_LOOKUP)
            ->where('driver_id', null)
            ->get();

        foreach ($pendingOrders as $order) {
            try {
                // Vérifier les tentatives d'invitation précédentes
                $previousInvitations = $order->orderInvitations()
                    ->where('is_waiting_acceptation', false)
                    ->count();

                // Si trop de tentatives, marquer comme "PERFORMER_NOT_FOUND"
                if ($previousInvitations >= 10) {
                    $order->update([
                        'status' => Order::PERFORMER_NOT_FOUND
                    ]);
                    Log::warning("Order {$order->id} failed to find driver after 10 attempts");
                    continue;
                }

                // Tenter d'assigner un nouveau driver
                $driver = $driverAssignmentService->assignNearestDriver($order);

                if ($driver) {
                    Log::info("Driver {$driver->id} assigned to order {$order->id}");
                } else {
                    // Replanifier le job pour plus tard si aucun driver trouvé
                    ProcessPendingOrderAssignments::dispatch()->delay(now()->addMinutes(5));
                }

            } catch (\Exception $e) {
                Log::error("Error processing order {$order->id}: " . $e->getMessage());
                $this->fail($e);
            }
        }
    }

    public function failed(\Exception $exception)
    {
        Log::error('ProcessPendingOrderAssignments job failed: ' . $exception->getMessage());
    }
}
