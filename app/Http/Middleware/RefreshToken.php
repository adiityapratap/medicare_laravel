<?php

namespace App\Http\Middleware;

use Closure;
use JWTAuth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class RefreshToken extends BaseMiddleware {

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $expired = false;
        $token = $this->auth->setRequest($request)->getToken();

        if (! $token ) {
            $status     = 400;
            $message    = 'Bad request, unable to process';
            return response()->json(compact('status','message'), $status);
        }

        try {
            $user = $this->auth->authenticate($token);  // Token is valid. User logged. Response without any token.
        } catch (TokenExpiredException $e) {
            $expired = true;
        } catch (JWTException $e) {
            $status     = 500;
            $message    = 'Please login again. Unable to process';
            return response()->json(compact('status','message'), $status);
        }

        if ($expired) {
            try {
                $payload = $this->auth->manager()->getPayloadFactory()->buildClaimsCollection()->toPlainArray();
                $key = 'block_refresh_token_for_user_' . $payload['sub'];
                $cachedBefore = (int) Cache::has($key);
                if ($cachedBefore) { // If a token alredy was refreshed and sent to the client in the last JWT_BLACKLIST_GRACE_PERIOD seconds.
                    \Auth::onceUsingId($payload['sub']); // Log the user using id.
                    return $next($request); // Token expired. Response without any token because in grace period.
                }

                $newToken = $this->auth->setRequest($request)
                  ->parseToken()
                  ->refresh();

                $gracePeriod = $this->auth->manager()->getBlacklist()->getGracePeriod();
                $expiresAt = Carbon::now()->addSeconds($gracePeriod);
                Cache::put($key, $newToken, $expiresAt);

                $user = $this->auth->authenticate($newToken);
            } catch (TokenExpiredException $e) {
                $status     = 401;
                $message    = 'Please login again. Your session has expired';
                return response()->json(compact('status','message'), $status);
            } catch (JWTException $e) {
                $status     = 500;
                $message    = 'Please login again. Unable to process';
                return response()->json(compact('status','message'), 200);
            }
             // send the refreshed token back to the client
            //  $request->headers->set('Authorization', 'Bearer ' . $newToken);
            $response = $next($request); // Token refreshed and continue.

            return $this->setAuthenticationHeader($response, $newToken); // Response with new token on header Authorization.
        }
        if (! $user) {
            $status     = 404;
            $message    = 'Invalid credentials, please try again.';
            return response()->json(compact('status','message'), $status);
        }

        return $next($request);
    }

}