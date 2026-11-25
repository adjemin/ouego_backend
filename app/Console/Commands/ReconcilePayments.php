<?php

namespace App\Console\Commands;

use App\Events\CustomerNotificationCreated;
use App\Models\CustomerNotification;
use App\Models\Invoice;
use App\Models\Order;
use App\Jobs\ProcessRefund;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Setting;
use App\Payments\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rattrapage des paiements, chaque minute :
 *  1. relit chez l'opérateur les paiements en attente (Jèko n'envoie aucun webhook d'échec, et un webhook peut se perdre) ;
 *  2. annule les commandes en ligne jamais payées après ONLINE_ORDER_PAYMENT_TIMEOUT_MINUTES ;
 *  3. rembourse les commandes en ligne annulées hors API (dashboard, base) dont le paiement a réussi ;
 *  4. relit les remboursements en cours de transfert et relance ceux dont l'essai suivant est dû ;
 *  alerte enfin sur les remboursements bloqués en cours de transfert depuis plus de 24 h.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Relit les paiements en attente chez leur opérateur et annule les commandes en ligne non payées';

    private const BATCH = 200;

    private const DEFAULT_TIMEOUT_MINUTES = 30;

    private const REFUND_BATCH = 100;

    /** Marque posée dans last_error d'un remboursement bloqué, pour n'alerter qu'une fois. */
    public const STUCK_REFUND_MARK = 'Bloqué en cours de transfert depuis plus de 24 h (alerte envoyée)';

    public function handle(PaymentService $payments): int
    {
        $this->refreshPendingPayments($payments);
        $this->refreshExpiredPayments($payments);
        $this->cancelUnpaidOnlineOrders();
        $this->refundCancelledPaidOrders($payments);
        $this->processRefunds($payments);

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

    /**
     * Un paiement expiré chez nous peut encore être payé chez l'opérateur (client resté sur la page) :
     * relu toutes les heures pendant 24 h, pour ne pas dépendre du seul webhook.
     */
    private function refreshExpiredPayments(PaymentService $payments): void
    {
        Payment::where('status', Payment::STATUS_EXPIRED)
            ->whereNotNull('provider')
            ->where('created_at', '>=', now()->subDay())
            ->where(fn (Builder $q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subHour()))
            ->orderBy('last_checked_at')
            ->limit(self::BATCH)
            ->get()
            ->each(function (Payment $payment) use ($payments) {
                try {
                    $payments->refresh($payment);
                } catch (\Throwable $e) {
                    Log::warning('payments:reconcile : statut illisible', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
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

    private function refundCancelledPaidOrders(PaymentService $payments): void
    {
        Order::whereIn('status', PaymentService::CLOSED_UNSERVED)
            ->where('payment_method_code', '<>', Order::PAYMENT_MODE_CASH)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('payments')
                ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                ->whereColumn('invoices.order_id', 'orders.id')
                ->where(fn ($q) => $q->whereNull('invoices.order_source')->orWhere('invoices.order_source', '<>', 'transactions'))
                ->where('payments.status', Payment::STATUS_SUCCESSFUL)
                ->whereNotNull('payments.provider')
                ->where('payments.amount', '>', 0)
                ->whereNull('payments.deleted_at')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('payment_refunds')
                    ->whereColumn('payment_refunds.payment_id', 'payments.id')))
            ->limit(self::REFUND_BATCH)
            ->get()
            ->each(function (Order $order) use ($payments) {
                try {
                    $payments->requestRefund($order);
                } catch (\Throwable $e) {
                    Log::error('payments:reconcile : remboursement impossible à créer', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }

    private function processRefunds(PaymentService $payments): void
    {
        PaymentRefund::where('status', PaymentRefund::PROCESSING)
            ->whereNotNull('provider_refund_id')
            ->orderBy('updated_at')
            ->limit(self::REFUND_BATCH)
            ->get()
            ->each(function (PaymentRefund $refund) use ($payments) {
                try {
                    $payments->refreshRefund($refund);
                } catch (\Throwable $e) {
                    Log::warning('payments:reconcile : transfert illisible', ['refund_id' => $refund->id, 'error' => $e->getMessage()]);
                }
            });

        // 409 sans webhook, ou statut de transfert illisible : personne ne rembourse, il faut le savoir
        PaymentRefund::where('status', PaymentRefund::PROCESSING)
            ->where('updated_at', '<=', now()->subDay())
            ->where(fn (Builder $q) => $q->whereNull('last_error')->orWhere('last_error', '<>', self::STUCK_REFUND_MARK))
            ->limit(self::REFUND_BATCH)
            ->get()
            ->each(function (PaymentRefund $refund) {
                Log::critical('Remboursement bloqué en cours de transfert', [
                    'refund_id' => $refund->id,
                    'payment_id' => $refund->payment_id,
                    'order_id' => $refund->order_id,
                    'amount' => $refund->amount,
                    'provider_refund_id' => $refund->provider_refund_id,
                    'reference' => $refund->providerReference(),
                ]);
                $refund->update(['last_error' => self::STUCK_REFUND_MARK]);
            });

        PaymentRefund::where('status', PaymentRefund::PENDING)
            ->where(fn (Builder $q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')
            ->limit(self::REFUND_BATCH)
            ->pluck('id')
            ->each(fn (int $id) => ProcessRefund::dispatch($id));
    }

    private function cancel(Order $order): void
    {
        // Sous verrou : un paiement confirmé au même instant voit la commande annulée et la rembourse
        $cancelled = DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== Order::INITIATED || $locked->is_completed
                || $locked->orderInvoice()?->status !== Invoice::UNPAID) {
                return false;
            }

            $locked->update([
                'status' => Order::CANCELLED,
                'is_completed' => true,
                'completion_time' => now(),
                'is_waiting' => false,
                'is_successful' => false,
            ]);
            $locked->newOrderHistory(Order::CANCELLED, 'system', null);

            return true;
        });

        if (! $cancelled) {
            return;
        }

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
