<?php

namespace App\Payments\Exceptions;

use RuntimeException;

/** L'opérateur a refusé ou n'a pas répondu ; le message est destiné aux logs. */
class GatewayException extends RuntimeException
{
}
