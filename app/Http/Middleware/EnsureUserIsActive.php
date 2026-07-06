<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Akun user ini sedang dinonaktifkan.',
                ], 403);
            }

            return redirect()->route('login')
                ->withErrors(['email' => 'Akun user ini sedang dinonaktifkan. Hubungi administrator.']);
        }

        return $next($request);
    }
}
