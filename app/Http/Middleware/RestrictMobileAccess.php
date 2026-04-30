<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictMobileAccess
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

        $isDashboardRoute = $routeName === 'mobile.dashboard' || $path === 'mobile/dashboard';
        $isQcRoute = str_starts_with($routeName, 'mobile.qc') || str_starts_with($path, 'mobile/qc');
        $isInboundScanRoute = str_starts_with($routeName, 'mobile.inbound-scan')
            || str_starts_with($path, 'mobile/inbound-scan');
        $isScanOutRoute = str_starts_with($routeName, 'mobile.scan-out')
            || str_starts_with($path, 'mobile/scan-out');
        $isPickingListRoute = (str_starts_with($routeName, 'mobile.') || str_starts_with($path, 'mobile'))
            && !$isInboundScanRoute
            && !$isQcRoute
            && !$isScanOutRoute
            && !$isDashboardRoute;
        $isOpnameRoute = str_starts_with($routeName, 'opname.') || str_starts_with($path, 'opname');
        $isLogoutRoute = $routeName === 'logout';
        $isDesktopQcRoute = str_starts_with($routeName, 'admin.outbound.qc-scan.')
            || str_starts_with($path, 'admin/outbound/qc-scan');

        if ($isDashboardRoute || $isLogoutRoute || $isOpnameRoute) {
            return $next($request);
        }

        $allowed = false;
        if ($hasPicker && $isPickingListRoute) {
            $allowed = true;
        }
        if ($hasQc && ($isQcRoute || $isDesktopQcRoute)) {
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

        return redirect()->route('mobile.dashboard');
    }
}
