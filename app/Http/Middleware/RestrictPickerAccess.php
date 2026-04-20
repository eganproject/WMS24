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
        $hasAdminScan = $roles->contains('admin-scan');
        $hasQc = $roles->contains('qc');
        $hasInboundScan = $roles->contains('inbound-scan');
        if (!$hasPicker && !$hasAdminScan && !$hasQc && !$hasInboundScan) {
            return $next($request);
        }

        $hasOtherRoles = $roles->diff(['picker', 'admin-scan', 'qc', 'inbound-scan'])->isNotEmpty();
        if ($hasOtherRoles) {
            return $next($request);
        }

        $routeName = $request->route()?->getName() ?? '';
        $path = trim($request->path(), '/');

        $isDashboardRoute = $routeName === 'picker.dashboard' || $path === 'picker/dashboard';
        $isQcRoute = str_starts_with($routeName, 'picker.qc') || str_starts_with($path, 'picker/qc');
        $isInboundScanRoute = str_starts_with($routeName, 'picker.inbound-scan')
            || str_starts_with($path, 'picker/inbound-scan');
        $isScanOutRoute = str_starts_with($routeName, 'picker.scan-out')
            || str_starts_with($path, 'picker/scan-out');
        $isPickingListRoute = (str_starts_with($routeName, 'picker.') || str_starts_with($path, 'picker'))
            && !$isInboundScanRoute
            && !$isQcRoute
            && !$isScanOutRoute
            && !$isDashboardRoute;
        $isOpnameRoute = str_starts_with($routeName, 'opname.') || str_starts_with($path, 'opname');
        $isLogoutRoute = $routeName === 'logout';

        if ($isDashboardRoute || $isLogoutRoute || $isOpnameRoute) {
            return $next($request);
        }

        $allowed = false;
        if ($hasPicker && $isPickingListRoute) {
            $allowed = true;
        }
        if ($hasQc && $isQcRoute) {
            $allowed = true;
        }
        if ($hasInboundScan && $isInboundScanRoute) {
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
