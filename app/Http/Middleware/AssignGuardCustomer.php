<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;

class AssignGuardCustomer
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $guard = null)
    {
        if($guard != null){
            //auth('api-customers')->shouldUse($guard);
            // Seule l'authentification est interceptée : une exception levée plus loin par le contrôleur
            // doit remonter en erreur serveur, pas être transformée en 401.
            try {
                $user = auth('api-customers')->userOrFail();
            } catch (Exception $e) {
                if ($e instanceof TokenInvalidException) {
                    return response()->json([
                        'code' => 401,
                        'status' => 'UNAUTHORIZED',
                        'success' => false,
                        'message' => 'Token is Invalid'
                    ],401);
                } else if ($e instanceof TokenExpiredException) {

                    return response()->json([
                        'code' => 401,
                        'status' => 'UNAUTHORIZED',
                        'success' => false,
                        'message' =>  'Token is Expired'
                    ],401);

                } else {

                    return response()->json([
                        'code' => 401,
                        'status' => 'UNAUTHORIZED',
                        'success' => false,
                        'message' =>  "Jeton d'authentification introuvable"
                    ],401);

                }
            }

            // Gestion des compte bloque
            if($user->is_blocked){
                return response()->json([
                    'code' => 403,
                    'status' => 'UNAUTHORIZED',
                    'success' => false,
                    'message' => 'Votre compte a été bloqué, veuillez contacter le support'
                ],403);
            }

            return $next($request);
        }else{
            return response()->json([
                'code' => 401,
                'status' => 'UNAUTHORIZED',
                'success' => false,
                'message' =>  "Jeton d'authentification introuvable"
            ],401);
        }


    }
}
