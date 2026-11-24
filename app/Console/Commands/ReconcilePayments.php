<?php

namespace App\Console\Commands;

use App\Events\CustomerNotificationCreated;
use App\Models\CustomerNotification;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Payments\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rattrapage des paiements, chaque minute :
 *  1. relit chez l'opérateur les paiements en attente (Jèko n'envoie aucun webhook d'échec, et un webhook peut se perdre) ;
 *  2. annule les commandes en ligne jamais payées après ONLINE_ORDER_PAYMENT_TIMEOUT_MINUTES.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Relit les paiements en attente chez leur opérateur et annule les commandes en ligne non payées';

    private const BATCH = 200;

    private const DEFAULT_TIMEOUT_MINUTES = 30;

    public function handle(PaymentService $payments): int
    {
        $this->refreshPendingPayments($payments);
        $this->cancelUnpaidOnlineOrders();

        return self::SUCCESS;
    }

    /**
     * Backoff : un paiement de moins de 10 min est relu chaque minute, ensuite toutes les 5 min.
     * Une erreur sur un paiement n'empêche pas de traiter les suivants.
     */
    private function refreshPendingPayments(PaymentService $payments): void
    {
        $now = now();

        Payment::where('status', Payment::STATUS_PENDING)
            ->whereNotNull('provider')
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('last_checked_at')
                    ->orWhere(fn (Builder $q) => $q->where('created_at', '>', $now->copy()->subMinutes(10))
                        ->where('last_checked_at', '<=', $now->copy()->subMinute()))
                    ->orWhere('last_checked_at', '<=', $now->copy()->subMinutes(5));
            })
            ->orderBy('last_checked_at')
            ->orderBy('id')
            ->limit(self::BATCH)
            ->get()
            ->each(function (Payment $payment) use ($payments) {
                try {
                    $payments->refresh($payment);
                } catch (\Throwable $e) {
                    Log::warning('payments:reconcile : statut illisible', [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }

    private function cancelUnpaidOnlineOrders(): void
    {
        $timeout = (int) Setting::get('ONLINE_ORDER_PAYMENT_TIMEOUT_MINUTES');
        if ($timeout <= 0) {
            $timeout = self::DEFAULT_TIMEOUT_MINUTES;
        }

        Order::where('status', Order::INITIATED)
            ->where('is_completed', false)
            ->where('payment_method_code', '<>', Order::PAYMENT_MODE_CASH)
            ->where('created_at', '<=', now()->subMinutes($timeout))
            // Facture de commande encore impayée
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('invoices')
                ->whereColumn('invoices.order_id', 'orders.id')
                ->where(fn ($q) => $q->whereNull('invoices.order_source')->orWhere('invoices.order_source', '<>', 'transactions'))
                ->where('invoices.status', Invoice::UNPAID))
            // Aucun paiement encore en cours chez l'opérateur
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('payments')
                ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                ->whereColumn('invoices.order_id', 'orders.id')
                ->where(fn ($q) => $q->whereNull('invoices.order_source')->orWhere('invoices.order_source', '<>', 'transactions'))
                ->where('payments.status', Payment::STATUS_PENDING)
                ->where('payments.expires_at', '>', now())
                ->whereNull('payments.deleted_at'))
            ->limit(self::BATCH)
            ->get()
            ->each(fn (Order $order) => $this->cancel($order));
    }

    private function cancel(Order $order): void
    {
        $order->update([
            'status' => Order::CANCELLED,
            'is_completed' => true,
            'completion_time' => now(),
            'is_waiting' => false,
            'is_successful' => false,
        ]);
        $order->newOrderHistory(Order::CANCELLED, 'system', null);

        $notification = CustomerNotification::create([
            'customer_id' => $order->customer_id,
            'title' => 'Commande annulée',
            'subtitle' => 'Commande annulée : paiement non reçu',
            'data_id' => $order->id,
            'type' => $order->getTable(),
            'is_read' => false,
            'is_received' => false,
            'meta_data' => null,
        ]);
        event(new CustomerNotificationCreated($notification));

        Log::info("payments:reconcile : commande #{$order->id} annulée, paiement non reçu");
    }
}
