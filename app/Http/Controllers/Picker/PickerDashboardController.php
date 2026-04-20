<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;

class PickerDashboardController extends Controller
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

        return view('picker.dashboard', [
            'routes' => [
                'opname' => route('opname.index'),
                'inboundScan' => route('picker.inbound-scan.index'),
                'qc' => route('picker.qc.index'),
                'scanOut' => route('picker.scan-out.index'),
                'pickingList' => route('picker.picking-list.index'),
                'logout' => route('logout'),
            ],
            'showInboundScan' => $hasInboundScan || $hasOtherRoles,
            'showQc' => $hasQc || $hasOtherRoles,
            'showScanOut' => $hasAdminScan || $hasOtherRoles,
            'showPickingList' => $hasPicker || $hasOtherRoles,
        ]);
    }
}
