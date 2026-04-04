<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Support\MenuPermissionResolver;

class PermissionMiddleware
{
    public function __construct(private MenuPermissionResolver $resolver)
    {
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        if (!$this->resolver->userCanForRequest($request)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}


