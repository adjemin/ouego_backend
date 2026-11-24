<?php

namespace App\Http\Controllers;

use InfyOm\Generator\Utils\ResponseUtil;

/**
 * @OA\Server(url="/api")
 * @OA\Info(
 *   title="InfyOm Laravel Generator APIs",
 *   version="1.0.0"
 * )
 * This class should be parent class for other API controllers
 * Class AppBaseController
 */
class AppBaseController extends Controller
{
    public function sendResponse($result, $message)
    {
        return response()->json(ResponseUtil::makeResponse($message, $result));
    }

    public function sendError($error, $code = 404)
    {
        return response()->json(ResponseUtil::makeError($error), $code);
    }

    /**
     * Réponse d'erreur d'un paiement en ligne : message en français pour l'app, détail technique dans les logs.
     */
    protected function paymentErrorResponse(\Throwable $e)
    {
        return match (true) {
            $e instanceof \App\Payments\Exceptions\PaymentRefused => $this->sendError($e->getMessage(), 400),
            $e instanceof \App\Payments\Exceptions\PaymentProviderUnavailable => $this->sendError('Le paiement en ligne est momentanément indisponible', 503),
            $e instanceof \App\Payments\Exceptions\GatewayException => $this->sendError("Le service de paiement n'a pas répondu, veuillez réessayer", 502),
            default => throw $e,
        };
    }

    public function sendSuccess($message)
    {
        return response()->json([
            'success' => true,
            'message' => $message
        ], 200);
    }
}
