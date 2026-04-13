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
        $hasPacker = $roles->contains('packer');
        $hasAdminScan = $roles->contains('admin-scan');
        $hasQc = $roles->contains('qc');
        $hasInboundScan = $roles->contains('inbound-scan');
        $hasOtherRoles = $roles->diff(['picker', 'packer', 'admin-scan', 'qc', 'inbound-scan'])->isNotEmpty();

        return view('picker.dashboard', [
            'routes' => [
                'opname' => route('opname.index'),
                'inboundScan' => route('picker.inbound-scan.index'),
                'picker' => route('picker.index'),
                'qc' => route('picker.qc.index'),
                'packer' => route('picker.packer.index'),
                'scanOut' => route('picker.scan-out.index'),
                'scanOutV2' => route('picker.scan-out-v2.index'),
                'pickingList' => route('picker.picking-list.index'),
                'logout' => route('logout'),
            ],
            'showPicking' => $hasPicker || $hasOtherRoles,
            'showInboundScan' => $hasInboundScan || $hasOtherRoles,
            'showQc' => $hasQc || $hasOtherRoles,
            'showPacking' => $hasPacker || $hasOtherRoles,
            'showScanOut' => $hasAdminScan || $hasOtherRoles,
            'showScanOutV2' => $hasAdminScan || $hasOtherRoles,
            'showPickingList' => $hasPicker || $hasOtherRoles,
        ]);
    }
}
