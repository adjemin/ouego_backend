<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\DeliveryType;
use App\Models\Service;
use App\Services\DriverAssignmentService;
use Illuminate\Support\Facades\Log;

class AssignNightOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:assign-night';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Attribue les commandes de nuit en attente aux chauffeurs disponibles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('AssignNightOrders: Lancement de l\'attribution des commandes de nuit en attente.');
        $orders = Order::where('status', Order::PERFORMER_LOOKUP)
            ->where('delivery_type_code', DeliveryType::TYPE_DE_NUIT)
            ->whereNull('driver_id')
            ->get();

        Log::info("Nombre de commandes de nuit en attente : " . $orders->count());

        if ($orders->isEmpty()) {
            $this->info('Aucune commande de nuit en attente.');
            return;
        }

        $driverService = app(DriverAssignmentService::class);
        $assigned = 0;

        foreach ($orders as $order) {
            try {
                $driverService->sendInvitations($order, 10);
                $assigned++;
                $this->info("Commande #{$order->id} ({$order->reference}) : recherche de chauffeurs lancée.");
            } catch (\Exception $e) {
                Log::error("Erreur attribution commande nuit #{$order->id}: " . $e->getMessage());
                $this->error("Commande #{$order->id} : erreur - {$e->getMessage()}");
            }
        }

        $this->info("Terminé. {$assigned}/{$orders->count()} commandes traitées.");
        Log::info("AssignNightOrders: Attribution des commandes de nuit en attente terminée. {$assigned}/{$orders->count()} commandes traitées.");

    }
}
