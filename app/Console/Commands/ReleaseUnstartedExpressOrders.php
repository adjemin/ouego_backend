<?php

namespace App\Console\Commands;

use App\Events\CustomerNotificationCreated;
use App\Events\OrderInvitationCancelled;
use App\Jobs\SendPushNotificationDriver;
use App\Models\CustomerNotification;
use App\Models\DriverDevice;
use App\Models\DriverNotification;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Models\Setting;
use App\Services\DriverAssignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Commandes EXPRESS acceptées mais non démarrées après le délai EXPRESS_START_TIMEOUT_MINUTES :
 * le chauffeur est retiré et la recherche relancée. Il n'est pas réinvité pour cette commande
 * (son invitation acceptée reste close).
 */
class ReleaseUnstartedExpressOrders extends Command
{
    const SETTING_KEY = 'EXPRESS_START_TIMEOUT_MINUTES';

    const DEFAULT_TIMEOUT_MINUTES = 15;

    protected $signature = 'orders:release-unstarted-express';

    protected $description = 'Retire le chauffeur des commandes Express acceptées mais non démarrées après le délai configuré, et relance la recherche';

    public static function timeoutMinutes(): int
    {
        $value = Setting::get(self::SETTING_KEY);

        return is_numeric($value) && intval($value) > 0 ? intval($value) : self::DEFAULT_TIMEOUT_MINUTES;
    }

    public function handle(): void
    {
        $timeout = self::timeoutMinutes();
        $deadline = now()->subMinutes($timeout);

        $orderIds = $this->releasableOrders($deadline)->pluck('id');

        if ($orderIds->isEmpty()) {
            $this->info('Aucune commande Express acceptée non démarrée à libérer.');
            return;
        }

        Log::info("ReleaseUnstartedExpressOrders: {$orderIds->count()} commande(s) Express non démarrée(s) depuis plus de {$timeout} min.");

        $released = 0;
        foreach ($orderIds as $orderId) {
            try {
                if ($this->release($orderId, $deadline)) {
                    $released++;
                }
            } catch (\Throwable $e) {
                Log::error("ReleaseUnstartedExpressOrders: commande #{$orderId} - {$e->getMessage()}");
                $this->error("Commande #{$orderId} : erreur - {$e->getMessage()}");
            }
        }

        $this->info("Terminé. {$released}/{$orderIds->count()} commande(s) réattribuée(s).");
    }

    private function release(int $orderId, $deadline): bool
    {
        // Verrou + mêmes critères revérifiés : le chauffeur a pu démarrer entre la sélection et le traitement
        $released = DB::transaction(function () use ($orderId, $deadline) {
            /** @var Order|null $order */
            $order = $this->releasableOrders($deadline)->whereKey($orderId)->lockForUpdate()->first();

            if ($order === null) {
                return null;
            }

            $driverId = $order->driver_id;

            OrderInvitation::deleteWaiting(
                OrderInvitation::where('order_id', $order->id),
                OrderInvitationCancelled::REASSIGNED
            );

            $order->update([
                'driver_id' => null,
                'status' => Order::PERFORMER_LOOKUP,
                'acceptation_time' => null,
            ]);

            $order->newOrderHistory(Order::PERFORMER_LOOKUP, 'system', null);

            return [$order, $driverId];
        });

        if ($released === null) {
            return false;
        }

        [$order, $driverId] = $released;

        Log::info("ReleaseUnstartedExpressOrders: commande #{$order->id} retirée au chauffeur #{$driverId}, recherche relancée.");

        $this->notifyDriver($driverId, $order);
        $this->notifyCustomer($order);

        app(DriverAssignmentService::class)->sendInvitations($order, 10);

        $this->info("Commande #{$order->id} ({$order->reference}) : chauffeur #{$driverId} retiré, recherche relancée.");

        return true;
    }

    /**
     * Commandes Express acceptées, non démarrées, dont l'acceptation est antérieure à $deadline.
     */
    private function releasableOrders($deadline)
    {
        return Order::express()
            ->active()
            ->notStarted()
            ->where('is_location', false)
            ->whereNotNull('driver_id')
            ->whereIn('status', [Order::PERFORMER_FOUND, Order::ACCEPTED])
            ->where('acceptation_time', '<=', $deadline);
    }

    private function notifyDriver(int $driverId, Order $order): void
    {
        $notification = DriverNotification::create([
            'driver_id' => $driverId,
            'title' => "Course #{$order->id} retirée",
            'subtitle' => "Vous n'avez pas démarré la course à temps, elle a été réattribuée",
            'data_id' => $order->id,
            'type' => $order->table,
            'is_read' => false,
            'is_received' => false,
            'meta_data' => null,
        ]);

        foreach (DriverDevice::where('driver_id', $driverId)->get() as $device) {
            try {
                SendPushNotificationDriver::dispatch($device->firebase_id, $notification);
            } catch (\Throwable $e) {
                Log::error("ReleaseUnstartedExpressOrders: push chauffeur #{$driverId} - {$e->getMessage()}");
            }
        }
    }

    private function notifyCustomer(Order $order): void
    {
        if (!$order->customer_id) {
            return;
        }

        $notification = CustomerNotification::create([
            'customer_id' => $order->customer_id,
            'title' => "Course #{$order->id} : recherche d'un nouveau chauffeur",
            'subtitle' => "Le chauffeur n'a pas démarré la course, nous en cherchons un autre",
            'data_id' => $order->id,
            'type' => $order->table,
            'is_read' => false,
            'is_received' => false,
            'meta_data' => null,
        ]);

        event(new CustomerNotificationCreated($notification));
    }
}
