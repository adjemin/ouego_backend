<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;
use App\Services\DriverAssignmentService;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Models\OrderInvitation;

// class ProcessPendingOrderAssignments implements ShouldQueue
class ProcessPendingOrderAssignments
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120];
    
    // Nombre maximum d'invitations avant abandon
    private const MAX_INVITATIONS = 10;
    
    // Délai d'attente pour une réponse (en minutes)
    private const INVITATION_TIMEOUT = 5;

    public function handle()
    {
        Log::info("ProcessPendingOrderAssignments: Debut de recherche de commandes en attentes");
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


        foreach ($pendingOrders as $order) {
            try {
                $this->processOrder($order);
            } catch (Throwable $e) {
                // Ne pas fail le job entier pour une seule commande
                Log::warning("ProcessPendingOrderAssignments: erreur lors de l'execution ".$e->getMessage());
            }
        }

        Log::info("ProcessPendingOrderAssignments: Fin de recherche de commandes en attentes");
    }

    private function processOrder(Order $order)
    {
        $waitingInvitations = $order->orderInvitations;
        $invitationCount = $waitingInvitations->count();

        // Cas 1 : Trop de tentatives - abandon
        if ($invitationCount >= self::MAX_INVITATIONS) {
            $this->markAsNotFound($order);
        }

        // Cas 2 : Aucune invitation - première tentative
        if ($invitationCount === 0) {
            $this->assignDriver($order);
        }

        // Cas 3 : Vérifier si les invitations ont expiré
        $expiredInvitations = $waitingInvitations->filter(function($invitation) {
            return $invitation->created_at->addMinutes(self::INVITATION_TIMEOUT)->isPast();
        });

        if ($expiredInvitations->isNotEmpty()) {
            // Supprimer SEULEMENT les invitations expirées
            OrderInvitation::whereIn('id', $expiredInvitations->pluck('id'))->delete();
            
            // Réessayer l'assignation
            $this->assignDriver($order);
        }
    }

    private function markAsNotFound(Order $order): void
    {
        $order->update([
            'status' => Order::PERFORMER_NOT_FOUND, 
            'is_waiting' => false,
            'is_completed' => true,
            'is_successful' =>false
        ]);
        $order->newOrderHistory(Order::PERFORMER_NOT_FOUND, 'system', null);
        
    }

    private function assignDriver(Order $order): void
    {
        // Recherche de chauffeurs et envoi d'invitations
        $expressService = app(DriverAssignmentService::class);
        $expressService->sendInvitations($order, 10);
    }

    public function failed(Throwable $exception)
    {
        // Gérer l'échec du job si nécessaire
    }
}
