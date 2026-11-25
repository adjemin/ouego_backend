<?php

namespace App\Payments\Exceptions;

use RuntimeException;

/** Aucun opérateur de paiement actif ou configuré. */
class PaymentProviderUnavailable extends RuntimeException
{
}
