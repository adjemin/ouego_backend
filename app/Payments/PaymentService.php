<?php

namespace App\Payments;

use App\Events\PaymentStatusUpdated;
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
    /** États qu'aucune confirmation ultérieure ne modifie. EXPIRED n'en fait pas partie : un paiement tardif reste crédité. */
    private const DEFINITIVE = [Payment::STATUS_SUCCESSFUL, Payment::STATUS_FAILED, Payment::STATUS_CANCELLED];

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
            if (! $locked || in_array($locked->status, self::DEFINITIVE, true)) {
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
                        $this->finish($locked, Payment::STATUS_FAILED, 'Montant reçu inférieur au montant attendu');
                        $this->markDepositFailed($locked->invoice_id);

                        return $locked;
                    }

                    $locked->payment_gateway_buyer_reference = $status->payerPhone ?? $locked->payment_gateway_buyer_reference;
                    $locked->payment_gateway_payment_method = $status->payerMethod ?? $locked->payment_gateway_payment_method;
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

            return;
        }

        $invoice->update(['status' => Invoice::PAID, 'is_paid_by_customer' => true]);

        if ($invoice->order_source === 'transactions') {
            $this->creditDeposit($invoice);
        }
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
