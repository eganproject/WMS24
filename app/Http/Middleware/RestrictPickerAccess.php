<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictPickerAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $roles = $user->roles()->pluck('slug');
        $hasPicker = $roles->contains('picker');
        $hasPacker = $roles->contains('packer');
        $hasAdminScan = $roles->contains('admin-scan');
        $hasQc = $roles->contains('qc');
        if (!$hasPicker && !$hasPacker && !$hasAdminScan && !$hasQc) {
            return $next($request);
        }

        $hasOtherRoles = $roles->diff(['picker', 'packer', 'admin-scan', 'qc'])->isNotEmpty();
        if ($hasOtherRoles) {
            return $next($request);
        }

        $routeName = $request->route()?->getName() ?? '';
        $path = trim($request->path(), '/');

        $isDashboardRoute = $routeName === 'picker.dashboard' || $path === 'picker/dashboard';
        $isPackerRoute = str_starts_with($routeName, 'picker.packer') || str_starts_with($path, 'picker/packer');
        $isQcRoute = str_starts_with($routeName, 'picker.qc') || str_starts_with($path, 'picker/qc');
        $isScanOutRoute = str_starts_with($routeName, 'picker.scan-out')
            || str_starts_with($routeName, 'picker.scan-out-v2')
            || str_starts_with($path, 'picker/scan-out')
            || str_starts_with($path, 'picker/scan-out-v2');
        $isPickerRoute = (str_starts_with($routeName, 'picker.') || str_starts_with($path, 'picker'))
            && !$isPackerRoute
            && !$isQcRoute
            && !$isScanOutRoute
            && !$isDashboardRoute;
        $isOpnameRoute = str_starts_with($routeName, 'opname.') || str_starts_with($path, 'opname');
        $isLogoutRoute = $routeName === 'logout';

        if ($isDashboardRoute || $isLogoutRoute || $isOpnameRoute) {
            return $next($request);
        }

        $allowed = false;
        if ($hasPicker && $isPickerRoute) {
            $allowed = true;
        }
        if ($hasQc && $isQcRoute) {
            $allowed = true;
        }
        if ($hasPacker && $isPackerRoute) {
            $allowed = true;
        }
        if ($hasAdminScan && $isScanOutRoute) {
            $allowed = true;
        }

        if ($allowed) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Akses dibatasi sesuai role operasional Anda.',
            ], 403);
        }

        return redirect()->route('picker.dashboard');
    }
}
