<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\UserNotDefinedException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenBlacklistedException;

use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
  use PHPOpenSourceSaver\JWTAuth\JWTAuth as NewJWTAuth;
use App\Models\DriverDevice;

class AssignGuardDriver
{

    private $jwtAuth;

    public function __construct(NewJWTAuth $jwtAuth)
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
            try {
                $accessToken = $request->bearerToken();
                if (!$accessToken) {
                    return $this->errorResponse('Authorization Token not found', 401);
                }
                
                $payload = $this->jwtAuth->setToken($accessToken)->getPayload();
                dd($payload->toArray());
                $driver = auth('api-drivers')->user();
                $device = DriverDevice::where('driver_id', $driver->id)->orderBy('created_at', 'desc')->first();
                // Check if the access token is valid
                if ($device && $accessToken && $device->firebase_id !== $payload['device_token'] ?? null) {
                    $this->jwtAuth->invalidate($accessToken);
                    return $this->errorResponse('Session expirée. Connectez-vous à nouveau.', 401);
                }
                
                return $next($request);
            } catch (Exception $e) {
                return $this->handleAuthException($request, $e, $next);
            }
        } else {
            return $this->errorResponse('Authorization Token not found', 401);
        }
    }

    private function handleAuthException(Request $request, Exception $e, $next)
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

        } else if ($e instanceof UserNotDefinedException) {
            return $this->errorResponse('User not found', 401);
        }
        else if ($e instanceof TokenBlacklistedException) {
            return $this->errorResponse('Token has been blacklisted', 401);
        }
        else {
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
