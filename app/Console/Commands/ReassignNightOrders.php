<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Models\DeliveryType;
use App\Models\Service;
use App\Services\DriverNuitAssignmentService;
use Illuminate\Support\Facades\Log;

class ReassignNightOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:reassign-night';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Réattribue les commandes de nuit non démarrées à 5h aux chauffeurs disponibles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Récupérer les commandes de nuit non démarrées
        $orders = Order::where('delivery_type_code', DeliveryType::TYPE_DE_NUIT)
            ->active()
            ->night()
            ->notStarted()
            ->whereIn('status', [Order::PERFORMER_LOOKUP, Order::PERFORMER_FOUND, Order::ACCEPTED])
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Aucune commande de nuit non démarrée à réattribuer.');
            return;
        }

        $nuitService = app(DriverNuitAssignmentService::class);
        $reassigned = 0;

        foreach ($orders as $order) {
            try {
                // Annuler les invitations en attente existantes
                OrderInvitation::where('order_id', $order->id)
                    ->where('is_waiting_acceptation', true)
                    ->update(['is_waiting_acceptation' => false]);

                // Réinitialiser le chauffeur assigné
                $order->update([
                    'driver_id' => null,
                    'status' => Order::PERFORMER_LOOKUP,
                ]);

                // Relancer la recherche de chauffeurs
                if ($order->service_slug == Service::COURSE || $order->service_slug == Service::LOCATION) {
                    $nuitService->assignCourseAndLocationNearestDriver($order, 10);
                }

                if ($order->service_slug == Service::AGREGATS_CONSTRUCTION) {
                    $nuitService->getAggregatDriverAndNotify($order);
                }

                $reassigned++;
                $this->info("Commande #{$order->id} ({$order->reference}) : réattribution lancée.");
            } catch (\Exception $e) {
                Log::error("Erreur réattribution commande nuit #{$order->id}: " . $e->getMessage());
                $this->error("Commande #{$order->id} : erreur - {$e->getMessage()}");
            }
        }

        $this->info("Terminé. {$reassigned}/{$orders->count()} commandes réattribuées.");
    }
}
