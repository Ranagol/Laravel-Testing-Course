<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        /**
         * It checks if the currently authenticated user (retrieved via auth()->user()) has an
         * is_admin property set to true. If the user is not an admin (i.e., is_admin is false), it
         * aborts the request with a 403 (Forbidden) HTTP response using abort(403).
         *
         * If the user is an admin (i.e., is_admin is true), the method allows the request to
         * continue down the middleware pipeline by calling $next($request).
         */
        if (!auth()->user()->is_admin) {
            abort(403);
        }

        return $next($request);
    }
}
