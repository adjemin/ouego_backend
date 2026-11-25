<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Payments\Data\GatewayWebhookEvent;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\InvalidWebhookSignature;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\PaymentGatewayManager;
use App\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhooks des opérateurs de paiement : POST /payments/webhook/{provider}.
 *
 * Le webhook n'est qu'un signal : après vérification de la signature, le statut est relu chez l'opérateur.
 * On répond 200 dès que la signature est valide, même si rien n'est trouvé ou si l'opérateur est injoignable,
 * pour ne pas faire désactiver le webhook (Jèko le coupe après 15 échecs) ; payments:reconcile conclura.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly PaymentService $payments,
    ) {
    }

    public function handle(string $provider, Request $request): JsonResponse
    {
        try {
            $gateway = $this->gateways->driver($provider);
        } catch (PaymentProviderUnavailable) {
            return response()->json(['status' => 'KO', 'message' => 'Opérateur inconnu'], 404);
        }

        try {
            $event = $gateway->parseWebhook($request);
        } catch (InvalidWebhookSignature $e) {
            Log::warning('Webhook de paiement : signature invalide', ['provider' => $provider, 'ip' => $request->ip()]);

            return response()->json(['status' => 'KO', 'message' => 'Signature invalide'], 401);
        }

        if ($event->event !== 'TRANSACTION_COMPLETED') {
            return response()->json(['status' => 'OK', 'message' => 'ignored']);
        }

        if ($event->kind === GatewayWebhookEvent::KIND_TRANSFER) {
            return $this->handleTransfer($provider, $event);
        }

        $payment = $this->findPayment($provider, $event);
        if (! $payment) {
            Log::warning('Webhook de paiement : paiement introuvable', [
                'provider' => $provider,
                'provider_payment_id' => $event->providerPaymentId,
                'reference' => $event->reference,
            ]);

            return response()->json(['status' => 'OK', 'message' => 'unknown payment']);
        }

        try {
            $this->payments->refresh($payment);
        } catch (GatewayException $e) {
            Log::error('Webhook de paiement : statut illisible chez l\'opérateur', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            // Jamais de 500 : Jèko couperait le webhook après 15 échecs ; payments:reconcile prendra le relais
            Log::critical('Webhook de paiement : erreur après validation du statut', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['status' => 'OK', 'message' => 'received']);
    }

    /** Transfert terminé : seul un remboursement OUEGO en est l'origine. */
    private function handleTransfer(string $provider, GatewayWebhookEvent $event): JsonResponse
    {
        $refund = ($event->providerPaymentId
                ? PaymentRefund::where('provider', $provider)->where('provider_refund_id', $event->providerPaymentId)->first()
                : null)
            ?? PaymentRefund::findByProviderReference($event->reference);

        if (! $refund || $refund->provider !== $provider) {
            Log::warning('Webhook de transfert : remboursement introuvable', [
                'provider' => $provider,
                'transfer_id' => $event->providerPaymentId,
                'reference' => $event->reference,
            ]);

            return response()->json(['status' => 'OK', 'message' => 'unknown transfer']);
        }

        // Transfert créé mais réponse perdue (409 au nouvel essai) : on rattache son id
        if (empty($refund->provider_refund_id) && $event->providerPaymentId
            && $event->reference === $refund->providerReference()) {
            $refund->update(['provider_refund_id' => $event->providerPaymentId, 'status' => PaymentRefund::PROCESSING]);
        }

        try {
            $this->payments->refreshRefund($refund);
        } catch (GatewayException $e) {
            Log::error('Webhook de transfert : statut illisible chez l\'opérateur', [
                'refund_id' => $refund->id,
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            // Jamais de 500 : Jèko couperait le webhook après 15 échecs ; payments:reconcile prendra le relais
            Log::critical('Webhook de paiement : erreur après validation du statut', [
                'refund_id' => $refund->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['status' => 'OK', 'message' => 'received']);
    }

    private function findPayment(string $provider, GatewayWebhookEvent $event): ?Payment
    {
        $query = Payment::where('provider', $provider);

        if ($event->providerPaymentId) {
            $payment = (clone $query)->where('provider_payment_id', $event->providerPaymentId)->first();
            if ($payment) {
                return $payment;
            }
        }

        return $event->reference ? $query->where('payment_reference', $event->reference)->first() : null;
    }
}
