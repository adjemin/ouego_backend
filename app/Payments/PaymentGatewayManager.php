<?php

namespace App\Payments;

use App\Models\Setting;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Exceptions\PaymentProviderUnavailable;

/**
 * Résout l'opérateur actif (setting PAYMENT_ACTIVE_PROVIDER) pour les nouveaux paiements,
 * et l'opérateur d'origine d'un paiement existant, même s'il n'est plus actif.
 */
class PaymentGatewayManager
{
    public function activeCode(): ?string
    {
        $code = Setting::get(config('payments.active_setting'));

        return empty($code) ? null : (string) $code;
    }

    public function active(): PaymentGateway
    {
        $code = $this->activeCode();
        if ($code === null) {
            throw new PaymentProviderUnavailable('Aucun opérateur de paiement actif');
        }

        $gateway = $this->driver($code);
        if (! $gateway->isConfigured()) {
            throw new PaymentProviderUnavailable("L'opérateur de paiement [{$code}] n'est pas configuré");
        }

        return $gateway;
    }

    public function driver(string $code): PaymentGateway
    {
        $class = config("payments.providers.{$code}.class");
        if (! $class) {
            throw new PaymentProviderUnavailable("Opérateur de paiement inconnu [{$code}]");
        }

        return app($class);
    }
}
