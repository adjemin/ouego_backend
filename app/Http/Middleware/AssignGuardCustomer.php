<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\JWTAuth;
use App\Models\CustomerDevice;

class AssignGuardCustomer
{
        private $jwtAuth;

        public function __construct(JWTAuth $jwtAuth)
        {
            $this->jwtAuth = $jwtAuth;
        }
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
            try {
                //$user = JWTAuth::parseToken()->authenticate();
                $user = auth('api-customers')->userOrFail();
                $accessToken = $request->bearerToken();
                $payload = $this->jwtAuth->getPayload($accessToken);
                $device = CustomerDevice::where('customer_id', $user->id)->orderBy('created_at', 'desc')->first();
                // Check if the access token is valid
                if ($device && $device->firebase_id !== $payload['device_token'] ?? null) {
                    $this->jwtAuth->invalidate($accessToken);
                    return response()->json('Session expirée. Connectez-vous à nouveau.', 401);
                }
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
