<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AssignGuardDriver
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
            try {
               $user = auth('api-drivers')->userOrFail();
               
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
            } catch (Exception $e) {

                return $this->handleAuthException($e);
            }
        } else {
            return $this->errorResponse('Authorization Token not found', 401);
        }
    }

    private function handleAuthException(Exception $e)
    {
        if ($e instanceof TokenInvalidException) {
            return $this->errorResponse('Token is Invalid', 401);
        } else if ($e instanceof TokenExpiredException) {
            try {
                $token = JWTAuth::refresh(JWTAuth::getToken());
                $user = JWTAuth::setToken($token)->toUser();
                $request->headers->set('Authorization', 'Bearer ' . $token);

                return $next($request);
            } catch (JWTException $e) {
                return $this->errorResponse('Token has expired and cannot be refreshed', 401);
            }
        } else {
            return $this->errorResponse('Authorization Token not found', 401);
        }
    }

    /**
     * Return a JSON error response.
     *
     * @param string $message
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    private function errorResponse($message, $code)
    {
        return response()->json([
            'code' => $code,
            'status' => 'UNAUTHORIZED',
            'success' => false,
            'message' => $message
        ], $code);
    }
}
