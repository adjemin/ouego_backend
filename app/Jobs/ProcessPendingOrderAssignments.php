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
use Throwable;
use Carbon\Carbon;
use App\Services\TripService;
use App\Models\Service;
use App\Models\OrderInvitation;

// class ProcessPendingOrderAssignments implements ShouldQueue
class ProcessPendingOrderAssignments
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $tries = 5;
    public $backoff = [30, 60, 120, 300, 600];
    
    // Nombre maximum d'invitations avant abandon
    private const MAX_INVITATIONS = 10;
    
    // Délai d'attente pour une réponse (en minutes)
    private const INVITATION_TIMEOUT = 5;

    public function handle(DriverAssignmentService $driverAssignmentService)
    {
        // Optimisation : eager loading pour éviter N+1
        $pendingOrders = Order::with(['orderInvitations' => function($query) {
                $query->where('is_waiting_acceptation', true);
            }])
            ->where('status', Order::PERFORMER_LOOKUP)
            ->whereNull('driver_id')
            ->where('is_draft', false)
            ->get();

        if ($pendingOrders->isEmpty()) {
            return;
        }

        $needsRecheck = false;

        foreach ($pendingOrders as $order) {
            try {
                $result = $this->processOrder($order, $driverAssignmentService);
                
                if ($result === 'needs_recheck') {
                    $needsRecheck = true;
                }
                
            } catch (Throwable $e) {
                // Ne pas fail le job entier pour une seule commande
            }
        }

        // ✅ Replanifier UNE SEULE FOIS après le traitement de toutes les commandes
        if ($needsRecheck) {
            ProcessPendingOrderAssignments::dispatch()->delay(now()->addMinutes(10));
        }
    }

    private function processOrder(Order $order, DriverAssignmentService $service): string
    {
        $waitingInvitations = $order->orderInvitations;
        $invitationCount = $waitingInvitations->count();

        // Cas 1 : Trop de tentatives - abandon
        if ($invitationCount >= self::MAX_INVITATIONS) {
            $this->markAsNotFound($order);
            return 'abandoned';
        }

        // Cas 2 : Aucune invitation - première tentative
        if ($invitationCount === 0) {
            $this->assignDriver($order, $service);
            return 'needs_recheck';
        }

        // Cas 3 : Vérifier si les invitations ont expiré
        $expiredInvitations = $waitingInvitations->filter(function($invitation) {
            return $invitation->created_at->addMinutes(self::INVITATION_TIMEOUT)->isPast();
        });

        if ($expiredInvitations->isNotEmpty()) {
            // Supprimer SEULEMENT les invitations expirées
            OrderInvitation::whereIn('id', $expiredInvitations->pluck('id'))->delete();
            
            // Réessayer l'assignation
            $this->assignDriver($order, $service);
            return 'needs_recheck';
        }

        // Cas 4 : En attente de réponse
        return 'waiting';
    }

    private function markAsNotFound(Order $order): void
    {
        $order->update(['status' => Order::PERFORMER_NOT_FOUND]);
        $order->newOrderHistory(Order::PERFORMER_NOT_FOUND, 'system', null);
        
    }

    private function assignDriver(Order $order, DriverAssignmentService $service): void
    {
        switch ($order->service_slug) {
            case Service::COURSE:
            case Service::LOCATION:
                $service->assignCourseAndLocationNearestDriver($order);
                break;
                
            case Service::AGREGATS_CONSTRUCTION:
                $service->getAggregatDriverAndNotify($order);
                break;
                
            default:
                break;
        }
    }

    public function failed(Throwable $exception)
    {
        // Gérer l'échec du job si nécessaire
    }
}
