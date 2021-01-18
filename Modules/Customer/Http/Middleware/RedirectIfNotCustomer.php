<?php

namespace Modules\Customer\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Traits\ApiResponseFormat;

/**
 * Reset Password controller for the Customer
 * @author    Hemant Achhami
 * @copyright 2020 Hazesoft Pvt Ltd
 */
class RedirectIfNotCustomer
{
    use ApiResponseFormat;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @param  string|null $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = 'customer')
    {
        if (!Auth::guard($guard)->check()) {
            return $this->errorResponse("Unauthenticated user", 401);
        }

        if (Auth::guard($guard)->user()->status == 0) {
            Auth::guard($guard)->logout();
            return $this->errorResponse("Customer disabled", 400);
        }

        return $next($request);
    }
}
