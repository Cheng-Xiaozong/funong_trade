<?php

namespace App\Http\Middleware;

use Closure;


class AdminToken
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
        config(['jwt.user' => 'App\Admin']);
        return $next($request);
    }
}
