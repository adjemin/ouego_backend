<?php

namespace App\Payments;

use App\Events\CustomerNotificationCreated;
use App\Events\PaymentStatusUpdated;
use App\Jobs\ProcessRefund;
use App\Models\PaymentRefund;
use App\Payments\Data\GatewayRefundResult;
use App\Models\CustomerNotification;
use App\Models\Order;
use App\Services\OrderDispatchService;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Transaction;
use App\Payments\Data\GatewayPaymentStatus;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\PaymentRefused;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seul endroit où un paiement change d'état et produit ses effets métier
 * (facture payée, crédit du portefeuille, recherche de chauffeur, remboursement).
 * Les opérateurs ne font que transporter ; webhook et polling ne font qu'appeler applyStatus().
 */
class PaymentService
{
    /** Commandes closes sans avoir été servies : un paiement qui leur arrive est remboursé. */
    public const CLOSED_UNSERVED = [Order::CANCELLED, Order::CANCELLED_WITH_PAYMENT, Order::CANCELLED_BY_TAXI, Order::FAILED];

    /** Délais avant chaque nouvel essai de remboursement, en minutes (le dernier se répète). */
    private const REFUND_BACKOFF = [5, 15, 30, 60];

    /** États qu'aucune confirmation ultérieure ne modifie. EXPIRED n'en fait pas partie : un paiement tardif reste crédité. */
    private const DEFINITIVE = [Payment::STATUS_SUCCESSFUL, Payment::STATUS_FAILED, Payment::STATUS_CANCELLED];

    /** Actions à lancer une fois la transaction validée (recherche de chauffeur, notifications). */
    private array $afterCommit = [];

    public function __construct(private readonly PaymentGatewayManager $gateways)
    {
    }

    /**
     * Ouvre (ou réutilise) le paiement d'une facture chez l'opérateur actif et renvoie le Payment avec son payment_url.
     *
     * @param  string  $userTable  customers|drivers : choisit le lien de retour vers la bonne app
     *
     * @throws \App\Payments\Exceptions\PaymentProviderUnavailable
     * @throws PaymentRefused
     * @throws GatewayException
     */
    public function start(Invoice $invoice, int $userId, string $method, string $userTable): Payment
    {
        $amount = (int) ceil((float) $invoice->total);

        if ($amount <= 0) {
            return $this->payWithoutProvider($invoice, $userId, $method);
        }

        $gateway = $this->gateways->active();

        if ($amount > $gateway->maxAmount()) {
            throw new PaymentRefused('Montant trop élevé pour le paiement en ligne, veuillez payer en espèces');
        }
        if (! in_array($method, array_column($gateway->supportedMethods(), 'code'), true)) {
            throw new PaymentRefused('Moyen de paiement non supporté');
        }

        $failure = null;

        $payment = DB::transaction(function () use ($invoice, $userId, $method, $amount, $gateway, &$failure) {
            // Verrou sur la facture : deux appels simultanés (double tap) ne créent pas deux paiements
            Invoice::whereKey($invoice->id)->lockForUpdate()->first();

            $pending = Payment::where('invoice_id', $invoice->id)
                ->where('status', Payment::STATUS_PENDING)
                ->where('provider', $gateway->code())
                ->where('payment_method_code', $method)
                ->where('expires_at', '>', now())
                ->whereNotNull('payment_url')
                ->latest('id')
                ->first();
            if ($pending) {
                return $pending;
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'user_id' => $userId,
                'payment_method_code' => $method,
                'payment_reference' => Payment::generateReference(),
                'amount' => $amount,
                'currency_code' => $invoice->currency_code ?? 'XOF',
                'status' => Payment::STATUS_PENDING,
                'is_waiting' => true,
                'is_completed' => false,
                'provider' => $gateway->code(),
                'expires_at' => now()->addMinutes((int) config('payments.pending_ttl_minutes')),
            ]);

            try {
                $checkout = $gateway->createPayment(
                    $payment,
                    $method,
                    url("payments/return/{$payment->payment_reference}/success"),
                    url("payments/return/{$payment->payment_reference}/error"),
                );
            } catch (GatewayException $e) {
                $failure = $e;
                $payment->update([
                    'status' => Payment::STATUS_FAILED,
                    'is_waiting' => false,
                    'is_completed' => true,
                    'payment_gateway_error_message' => mb_substr($e->getMessage(), 0, 255),
                ]);
                $this->markDepositFailed($invoice);

                return $payment;
            }

            $payment->update([
                'provider_payment_id' => $checkout->providerPaymentId,
                'payment_url' => $checkout->paymentUrl,
                'expires_at' => $checkout->expiresAt ?? $payment->expires_at,
            ]);

            return $payment;
        });

        if ($failure) {
            Log::error('Paiement : création refusée par l\'opérateur', ['payment_id' => $payment->id, 'error' => $failure->getMessage()]);
            throw $failure;
        }

        return $payment->fresh();
    }

    /**
     * Applique le statut rapporté par l'opérateur. Idempotent : le paiement est verrouillé et un état
     * définitif n'est jamais modifié, donc webhook, polling et rejeux ne produisent les effets qu'une fois.
     */
    public function applyStatus(Payment $payment, GatewayPaymentStatus $status): void
    {
        $event = DB::transaction(function () use ($payment, $status) {
            /** @var Payment $locked */
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (! $locked) {
                return null;
            }

            if (in_array($locked->status, self::DEFINITIVE, true)) {
                // L'opérateur a encaissé un paiement que nous avions clos sans crédit (échec, annulation,
                // montant incomplet) : l'argent est rendu, jamais gardé en silence
                if ($status->status === GatewayPaymentStatus::SUCCESSFUL && $locked->status !== Payment::STATUS_SUCCESSFUL
                    && ! PaymentRefund::where('payment_id', $locked->id)->exists()) {
                    Log::critical('Paiement encaissé par l\'opérateur sur un paiement déjà clos : remboursement', [
                        'payment_id' => $locked->id,
                        'local_status' => $locked->status,
                        'amount' => $status->amount,
                    ]);
                    $this->rememberPayer($locked, $status);
                    $this->afterCommit[] = fn () => $this->refundPayment($locked->fresh(), null, $status->amount);
                }

                return null;
            }

            $locked->last_checked_at = now();

            switch ($status->status) {
                case GatewayPaymentStatus::SUCCESSFUL:
                    if ($status->amount !== null && $status->amount < (int) $locked->amount) {
                        Log::critical('Paiement : montant confirmé inférieur au montant attendu', [
                            'payment_id' => $locked->id,
                            'expected' => $locked->amount,
                            'received' => $status->amount,
                        ]);
                        $this->rememberPayer($locked, $status);
                        $this->finish($locked, Payment::STATUS_FAILED, 'Montant reçu inférieur au montant attendu');
                        $this->markDepositFailed($locked->invoice_id);
                        // Ce qui a été reçu est rendu ; le client peut payer à nouveau
                        if ($status->amount > 0) {
                            $this->afterCommit[] = fn () => $this->refundPayment($locked->fresh(), null, $status->amount);
                        }

                        return $locked;
                    }

                    $this->rememberPayer($locked, $status);
                    $this->finish($locked, Payment::STATUS_SUCCESSFUL);
                    $this->applySuccessEffects($locked);

                    return $locked;

                case GatewayPaymentStatus::FAILED:
                    $this->finish($locked, Payment::STATUS_FAILED);
                    $this->markDepositFailed($locked->invoice_id);

                    return $locked;

                default:
                    $locked->save();

                    return null;
            }
        });

        if ($event) {
            $this->broadcast($event);
        }

        $this->runAfterCommit();
    }

    private function rememberPayer(Payment $payment, GatewayPaymentStatus $status): void
    {
        $payment->payment_gateway_buyer_reference = $status->payerPhone ?? $payment->payment_gateway_buyer_reference;
        $payment->payment_gateway_payment_method = $status->payerMethod ?? $payment->payment_gateway_payment_method;
        $payment->save();
    }

    private function runAfterCommit(): void
    {
        $actions = $this->afterCommit;
        $this->afterCommit = [];
        foreach ($actions as $action) {
            $action();
        }
    }

    /**
     * Relit le statut chez l'opérateur d'origine du paiement et l'applique ; marque EXPIRED un paiement
     * toujours en attente après expires_at.
     *
     * @throws GatewayException
     */
    public function refresh(Payment $payment): void
    {
        if (empty($payment->provider)) {
            return;
        }

        $payment->increment('check_attempts');
        $payment->forceFill(['last_checked_at' => now()])->save();

        $status = $this->gateways->driver($payment->provider)->fetchPaymentStatus($payment);
        $this->applyStatus($payment, $status);

        $payment->refresh();
        if ($payment->status === Payment::STATUS_PENDING && $payment->expires_at && $payment->expires_at->isPast()) {
            $this->expire($payment);
        }
    }

    private function expire(Payment $payment): void
    {
        $expired = DB::transaction(function () use ($payment) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if ($locked->status !== Payment::STATUS_PENDING) {
                return null;
            }
            $this->finish($locked, Payment::STATUS_EXPIRED);
            $this->markDepositFailed($locked->invoice_id);

            return $locked;
        });

        if ($expired) {
            $this->broadcast($expired);
        }
    }

    /** Facture à 0 F (remise totale) : payée sans passer par l'opérateur. */
    private function payWithoutProvider(Invoice $invoice, int $userId, string $method): Payment
    {
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $userId,
            'payment_method_code' => $method,
            'payment_reference' => Payment::generateReference(),
            'amount' => 0,
            'currency_code' => $invoice->currency_code ?? 'XOF',
            'status' => Payment::STATUS_PENDING,
            'is_waiting' => true,
            'is_completed' => false,
        ]);

        $this->applyStatus($payment, new GatewayPaymentStatus(GatewayPaymentStatus::SUCCESSFUL, 0));

        return $payment->fresh();
    }

    private function finish(Payment $payment, string $status, ?string $error = null): void
    {
        $payment->status = $status;
        $payment->is_waiting = false;
        $payment->is_completed = true;
        if ($error !== null) {
            $payment->payment_gateway_error_message = $error;
        }
        $payment->save();
    }

    /** Effets d'un paiement réussi, appelés une seule fois sous le verrou du paiement. */
    private function applySuccessEffects(Payment $payment): void
    {
        /** @var Invoice|null $invoice */
        $invoice = Invoice::whereKey($payment->invoice_id)->lockForUpdate()->first();
        if (! $invoice) {
            Log::critical('Paiement réussi sans facture', ['payment_id' => $payment->id]);

            return;
        }

        if ($invoice->status !== Invoice::UNPAID) {
            // Un autre paiement a déjà réglé la facture : l'argent est encaissé deux fois
            Log::critical('Paiement réussi sur une facture déjà réglée', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'invoice_status' => $invoice->status,
            ]);
            $this->afterCommit[] = fn () => $this->refundPayment($payment->fresh(), null);

            return;
        }

        $invoice->update(['status' => Invoice::PAID, 'is_paid_by_customer' => true]);

        if ($invoice->order_source === 'transactions') {
            $this->creditDeposit($invoice);

            return;
        }

        $this->releaseOrder($invoice, $payment);
    }

    /**
     * Commande payée : la recherche de chauffeur démarre, hors transaction (envoi d'invitations et de notifications).
     * La commande est verrouillée : une annulation concurrente passe avant ou après, jamais entre les deux.
     * Une commande close sans avoir été servie (annulée, en échec) est remboursée au lieu d'être relancée.
     */
    private function releaseOrder(Invoice $invoice, Payment $payment): void
    {
        $order = Order::whereKey($invoice->order_id)->lockForUpdate()->first();
        if (! $order) {
            Log::critical('Paiement réussi sur une facture sans commande', ['payment_id' => $payment->id, 'invoice_id' => $invoice->id]);

            return;
        }

        if (in_array($order->status, self::CLOSED_UNSERVED, true)) {
            $this->afterCommit[] = fn () => $this->requestRefund($order->fresh());

            return;
        }

        if ($order->status !== Order::INITIATED || $order->is_completed) {
            // Commande déjà en cours ou servie : le paiement la règle, rien à relancer
            Log::warning('Paiement reçu pour une commande déjà engagée', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'status' => $order->status,
            ]);

            return;
        }

        $this->afterCommit[] = function () use ($order) {
            $order = $order->fresh();

            // Annulée entre la validation du paiement et ce lancement : remboursement
            if (! app(OrderDispatchService::class)->startDriverLookup($order, 'system', null)) {
                if (in_array($order->status, self::CLOSED_UNSERVED, true)) {
                    $this->requestRefund($order);
                }

                return;
            }

            $notification = CustomerNotification::create([
                'customer_id' => $order->customer_id,
                'title' => 'Paiement reçu',
                'subtitle' => 'Paiement reçu, recherche de chauffeur en cours',
                'data_id' => $order->id,
                'type' => $order->getTable(),
                'is_read' => false,
                'is_received' => false,
                'meta_data' => null,
            ]);
            event(new CustomerNotificationCreated($notification));
        };
    }

    // ---------------------------------------------------------------
    // Remboursements
    // ---------------------------------------------------------------

    /**
     * Commande en ligne annulée : rembourse intégralement chaque paiement réussi de sa facture, par son opérateur
     * d'origine. La commande passe en CANCELLED_WITH_PAYMENT et la facture en REFUNDED. Idempotent.
     */
    public function requestRefund(Order $order): ?PaymentRefund
    {
        if (! $order->isPaidOnline()) {
            return null;
        }

        $invoice = $order->orderInvoice();
        if (! $invoice) {
            return null;
        }

        $payments = Payment::where('invoice_id', $invoice->id)
            ->where('status', Payment::STATUS_SUCCESSFUL)
            ->whereNotNull('provider')
            ->where('amount', '>', 0)
            ->get();
        if ($payments->isEmpty()) {
            return null;
        }

        $refund = null;
        foreach ($payments as $payment) {
            $refund = $this->refundPayment($payment, $order);
        }

        DB::transaction(function () use ($order, $invoice) {
            Order::whereKey($order->id)->whereIn('status', [Order::CANCELLED, Order::CANCELLED_WITH_PAYMENT])
                ->update(['status' => Order::CANCELLED_WITH_PAYMENT]);
            Invoice::whereKey($invoice->id)->update(['status' => Invoice::REFUNDED]);
        });

        return $refund;
    }

    /**
     * Crée (une seule fois par paiement) le remboursement d'un paiement et l'envoie à son opérateur.
     * $amount : montant réellement encaissé s'il diffère de celui du paiement (par défaut, le paiement entier).
     */
    public function refundPayment(Payment $payment, ?Order $order, ?int $amount = null): PaymentRefund
    {
        $created = false;

        $refund = DB::transaction(function () use ($payment, $order, $amount, &$created) {
            Payment::whereKey($payment->id)->lockForUpdate()->first();

            $existing = PaymentRefund::where('payment_id', $payment->id)->first();
            if ($existing) {
                return $existing;
            }

            $created = true;

            return PaymentRefund::create([
                'payment_id' => $payment->id,
                'order_id' => $order?->id,
                'provider' => $payment->provider,
                'amount' => $amount ?? (int) $payment->amount,
                'currency_code' => $payment->currency_code ?? 'XOF',
                'reference' => 'RF-'.$payment->payment_reference,
                'recipient_phone' => $payment->payment_gateway_buyer_reference ?: $this->payerPhone($payment),
                'recipient_method' => $payment->payment_gateway_payment_method ?: $payment->payment_method_code,
                'status' => PaymentRefund::PENDING,
            ]);
        });

        if ($created) {
            ProcessRefund::dispatch($refund->id);
        }

        return $refund->fresh();
    }

    /** Numéro du compte OUEGO qui a payé, si l'opérateur n'a pas renvoyé celui du payeur. */
    private function payerPhone(Payment $payment): ?string
    {
        $invoice = Invoice::find($payment->invoice_id);
        if ($invoice?->order_source === 'transactions'
            && Transaction::find($invoice->order_id)?->user_source === 'drivers') {
            return Driver::find($payment->user_id)?->phone;
        }

        return Customer::find($payment->user_id)?->phone;
    }

    /** Envoie un remboursement PENDING arrivé à échéance. Le verrou empêche deux envois simultanés. */
    public function processRefund(int $refundId): void
    {
        $succeeded = DB::transaction(function () use ($refundId) {
            /** @var PaymentRefund|null $refund */
            $refund = PaymentRefund::whereKey($refundId)->lockForUpdate()->first();
            if (! $refund || $refund->status !== PaymentRefund::PENDING
                || ($refund->next_attempt_at && $refund->next_attempt_at->isFuture())) {
                return false;
            }

            $refund->attempts++;

            try {
                $result = $this->gateways->driver($refund->provider)->refund($refund, $refund->payment);
            } catch (\Throwable $e) {
                // Issue inconnue : le transfert existe peut-être, le prochain essai garde la même référence
                $this->scheduleRefundRetry($refund, $e->getMessage(), false);

                return false;
            }

            return $this->applyRefundResult($refund, $result);
        });

        if ($succeeded) {
            $this->notifyRefunded(PaymentRefund::find($refundId));
        }
    }

    /** Relit chez l'opérateur un remboursement en cours de transfert. */
    public function refreshRefund(PaymentRefund $refund): void
    {
        $succeeded = DB::transaction(function () use ($refund) {
            /** @var PaymentRefund|null $locked */
            $locked = PaymentRefund::whereKey($refund->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== PaymentRefund::PROCESSING || empty($locked->provider_refund_id)) {
                return false;
            }

            return $this->applyRefundResult($locked, $this->gateways->driver($locked->provider)->fetchRefundStatus($locked));
        });

        if ($succeeded) {
            $this->notifyRefunded($refund->fresh());
        }
    }

    /** Applique la réponse de l'opérateur ; renvoie true si le remboursement vient d'aboutir. */
    private function applyRefundResult(PaymentRefund $refund, GatewayRefundResult $result): bool
    {
        if ($result->status === PaymentRefund::FAILED) {
            // Refus définitif : le prochain essai crée un nouveau transfert, donc une nouvelle référence
            $this->scheduleRefundRetry($refund, $result->error ?? 'Transfert refusé', true);

            return false;
        }

        $refund->provider_refund_id = $result->providerRefundId ?? $refund->provider_refund_id;
        $refund->status = $result->status;
        $refund->next_attempt_at = null;
        $refund->save();

        return $result->status === PaymentRefund::SUCCESSFUL;
    }

    private function scheduleRefundRetry(PaymentRefund $refund, string $error, bool $newTransfer): void
    {
        $refund->last_error = mb_substr($error, 0, 1000);

        if ($newTransfer) {
            $refund->transfer_sequence = ($refund->transfer_sequence ?? 1) + 1;
            $refund->provider_refund_id = null;
        }

        if ($refund->attempts >= (int) config('payments.refund_max_attempts')) {
            $refund->status = PaymentRefund::FAILED;
            $refund->next_attempt_at = null;
            $refund->save();
            Log::critical('Remboursement en échec', [
                'refund_id' => $refund->id,
                'payment_id' => $refund->payment_id,
                'order_id' => $refund->order_id,
                'amount' => $refund->amount,
                'error' => $refund->last_error,
            ]);

            return;
        }

        $delay = self::REFUND_BACKOFF[min(max($refund->attempts - 1, 0), count(self::REFUND_BACKOFF) - 1)];
        $refund->status = PaymentRefund::PENDING;
        $refund->next_attempt_at = now()->addMinutes($delay);
        $refund->save();
    }

    private function notifyRefunded(?PaymentRefund $refund): void
    {
        $payment = $refund?->payment;
        if (! $payment) {
            return;
        }

        $subtitle = 'Votre paiement de '.number_format($refund->amount, 0, ',', ' ').' F a été remboursé';
        $invoice = Invoice::find($payment->invoice_id);

        if ($invoice?->order_source === 'transactions'
            && Transaction::find($invoice->order_id)?->user_source === 'drivers') {
            $notification = \App\Models\DriverNotification::create([
                'driver_id' => $payment->user_id,
                'title' => 'Remboursement effectué',
                'subtitle' => $subtitle,
                'data_id' => $refund->id,
                'type' => $refund->getTable(),
                'is_read' => false,
                'is_received' => false,
            ]);
            foreach (\App\Models\DriverDevice::where('driver_id', $payment->user_id)->get() as $device) {
                try {
                    \App\Jobs\SendPushNotificationDriver::dispatch($device->firebase_id, $notification);
                } catch (\Throwable $e) {
                    Log::error('Remboursement : push chauffeur impossible', ['refund_id' => $refund->id, 'error' => $e->getMessage()]);
                }
            }

            return;
        }

        $notification = CustomerNotification::create([
            'customer_id' => $payment->user_id,
            'title' => 'Remboursement effectué',
            'subtitle' => $subtitle,
            'data_id' => $refund->order_id ?? $refund->id,
            'type' => $refund->order_id ? 'orders' : $refund->getTable(),
            'is_read' => false,
            'is_received' => false,
            'meta_data' => null,
        ]);
        event(new CustomerNotificationCreated($notification));
    }

    private function creditDeposit(Invoice $invoice): void
    {
        $transaction = Transaction::whereKey($invoice->order_id)->lockForUpdate()->first();
        if (! $transaction || $transaction->type !== Transaction::TYPE_DEPOSIT) {
            return;
        }

        $transaction->update(['status' => Transaction::SUCCESSFUL]);

        $user = $transaction->user_source === 'drivers'
            ? Driver::find($invoice->customer_id)
            : Customer::find($invoice->customer_id);

        $user?->creditBalance($transaction->amount);
    }

    /** Une recharge dont le paiement échoue ou expire passe en FAILED. */
    private function markDepositFailed(Invoice|int|null $invoice): void
    {
        $invoice = $invoice instanceof Invoice ? $invoice : Invoice::find($invoice);
        if (! $invoice || $invoice->order_source !== 'transactions' || $invoice->status !== Invoice::UNPAID) {
            return;
        }

        Transaction::whereKey($invoice->order_id)
            ->where('type', Transaction::TYPE_DEPOSIT)
            ->where('status', '!=', Transaction::SUCCESSFUL)
            ->update(['status' => Transaction::FAILED]);
    }

    private function broadcast(Payment $payment): void
    {
        $invoice = Invoice::find($payment->invoice_id);
        if (! $invoice || ! $payment->user_id) {
            return;
        }

        if ($invoice->order_source === 'transactions') {
            $userTable = Transaction::find($invoice->order_id)?->user_source === 'drivers' ? 'drivers' : 'customers';
            PaymentStatusUpdated::dispatch($payment, $userTable, null);

            return;
        }

        PaymentStatusUpdated::dispatch($payment, 'customers', $invoice->order_id);
    }
}
