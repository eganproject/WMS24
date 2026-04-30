<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;

class MobileDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $roles = $user ? $user->roles()->pluck('slug') : collect();
        $hasPicker = $roles->contains('picker');
        $hasAdminScan = $roles->contains('admin-scan');
        $hasQc = $roles->contains('qc');
        $hasInboundScan = $roles->contains('inbound-scan');
        $hasOtherRoles = $roles->diff(['picker', 'admin-scan', 'qc', 'inbound-scan'])->isNotEmpty();

        return view('mobile.dashboard', [
            'routes' => [
                'opname' => route('opname.index'),
                'inboundScan' => route('mobile.inbound-scan.index'),
                'qc' => route('mobile.qc.index'),
                'scanOut' => route('mobile.scan-out.index'),
                'pickingList' => route('mobile.picking-list.index'),
                'logout' => route('logout'),
            ],
            'showInboundScan' => $hasInboundScan || $hasOtherRoles,
            'showQc' => $hasQc || $hasOtherRoles,
            'showScanOut' => $hasAdminScan || $hasOtherRoles,
            'showPickingList' => $hasPicker || $hasOtherRoles,
        ]);
    }
}
