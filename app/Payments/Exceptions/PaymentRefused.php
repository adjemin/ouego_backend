<?php

namespace App\Payments\Exceptions;

use RuntimeException;

/** Paiement refusé pour une règle métier ; le message est montré au client. */
class PaymentRefused extends RuntimeException
{
}
