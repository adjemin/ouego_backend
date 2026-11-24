<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Contracts\View\View;

/**
 * Page HTTPS où l'opérateur renvoie le client après paiement (successUrl / errorUrl), qui le redirige vers l'app.
 * Elle n'a aucun effet sur le paiement : seuls le webhook signé et le polling font foi.
 */
class PaymentReturnController extends Controller
{
    public function show(string $reference, string $result): View
    {
        abort_unless(in_array($result, ['success', 'error'], true), 404);

        $deeplink = null;
        $payment = Payment::where('payment_reference', $reference)->first();
        if ($payment) {
            $isDeposit = Invoice::whereKey($payment->invoice_id)->value('order_source') === 'transactions';
            $base = config('payments.return_deeplinks.'.($isDeposit ? 'drivers' : 'customers'));
            if ($base) {
                $deeplink = $base.(str_contains($base, '?') ? '&' : '?').http_build_query([
                    'reference' => $reference,
                    'result' => $result,
                ]);
            }
        }

        return view('payments.return', [
            'success' => $result === 'success',
            'deeplink' => $deeplink,
        ]);
    }
}
