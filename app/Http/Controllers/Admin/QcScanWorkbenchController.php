<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Mobile\QcScanController as BaseQcScanController;

class QcScanWorkbenchController extends BaseQcScanController
{
    public function index()
    {
        return view('admin.outbound.qc-scan.index', [
            'routes' => [
                'scanResi' => route('admin.outbound.qc-scan.scan'),
                'scanSku' => route('admin.outbound.qc-scan.scan-sku'),
                'hold' => route('admin.outbound.qc-scan.hold'),
                'complete' => route('admin.outbound.qc-scan.complete'),
                'reset' => route('admin.outbound.qc-scan.reset'),
                'history' => route('admin.outbound.qc-history.index'),
                'scanOutHistory' => route('admin.outbound.scan-out-history.index'),
            ],
        ]);
    }
}
