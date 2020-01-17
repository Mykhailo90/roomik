<?php

namespace App\Http\Middleware;

use App\Http\Controllers\API\BaseController;
use App\User;
use Closure;

class CustomAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $access_token = $request->header('token');
        $user = User::findByToken($access_token);

        if (is_null($user)) {
            return BaseController::sendError('Unauthorised.', 'Invalid Token');
        }

        $request->user = $user;

        return $next($request);
    }
}
