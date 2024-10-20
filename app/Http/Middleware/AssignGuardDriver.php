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
            //auth()->shouldUse($guard);
            try {
               // $user = JWTAuth::parseToken()->authenticate();
               $user = auth('api-drivers')->userOrFail();
                return $next($request);
            } catch (Exception $e) {
                if ($e instanceof TokenInvalidException) {
                    return response()->json([
                        'code' => 401,
                        'status' => 'UNAUTHORIZED',
                        'success' => false,
                        'message' => 'Token is Invalid'
                    ],401);
                } else if ($e instanceof TokenExpiredException) {

                    // Tentative de rafraîchissement du token
                    try {
                        $token = JWTAuth::refresh(JWTAuth::getToken());
                        $user = JWTAuth::setToken($token)->toUser();
                        $request->headers->set('Authorization', 'Bearer ' . $token);

                        return $next($request);
                    } catch (JWTException $e) {
                        return response()->json([
                            'code' => 401,
                            'status' => 'UNAUTHORIZED',
                            'success' => false,
                            'message' => 'Token has expired and cannot be refreshed'
                        ], 401);
                    }

                } else {

                    return response()->json([
                        'code' => 401,
                        'status' => 'UNAUTHORIZED',
                        'success' => false,
                        'message' =>  'Authorization Token not found'
                    ],401);

                }
            }
        }else{
            return response()->json([
                'code' => 401,
                'status' => 'UNAUTHORIZED',
                'success' => false,
                'message' =>  'Authorization Token not found'
            ],401);
        }


    }
}
