<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\DeliveryType;
use App\Models\Service;
use App\Services\DriverNuitAssignmentService;
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
        $orders = Order::where('status', Order::PERFORMER_LOOKUP)
            ->where('delivery_type_code', DeliveryType::TYPE_DE_NUIT)
            ->whereNull('driver_id')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Aucune commande de nuit en attente.');
            return;
        }

        $nuitService = app(DriverNuitAssignmentService::class);
        $assigned = 0;

        foreach ($orders as $order) {
            try {
                if ($order->service_slug == Service::COURSE || $order->service_slug == Service::LOCATION) {
                    $nuitService->assignCourseAndLocationNearestDriver($order, 10);
                }

                if ($order->service_slug == Service::AGREGATS_CONSTRUCTION) {
                    $nuitService->getAggregatDriverAndNotify($order, 10);
                }

                $assigned++;
                $this->info("Commande #{$order->id} ({$order->reference}) : recherche de chauffeurs lancée.");
            } catch (\Exception $e) {
                Log::error("Erreur attribution commande nuit #{$order->id}: " . $e->getMessage());
                $this->error("Commande #{$order->id} : erreur - {$e->getMessage()}");
            }
        }

        $this->info("Terminé. {$assigned}/{$orders->count()} commandes traitées.");
    }
}
