<?php

namespace App\Payments\Exceptions;

use RuntimeException;

/** Webhook dont la signature est absente ou invalide. */
class InvalidWebhookSignature extends RuntimeException
{
}
