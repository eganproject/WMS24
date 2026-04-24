<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Picker\InboundScanController as BaseInboundScanController;

class InboundScanWorkbenchController extends BaseInboundScanController
{
    public function index()
    {
        return view('admin.inbound.scan.index', [
            'routes' => [
                'search' => route('admin.inbound.scan.transactions'),
                'open' => route('admin.inbound.scan.open'),
                'scanSku' => route('admin.inbound.scan.scan-sku'),
                'complete' => route('admin.inbound.scan.complete'),
                'reset' => route('admin.inbound.scan.reset'),
                'receipts' => route('admin.inbound.receipts.index'),
                'manuals' => route('admin.inbound.manuals.index'),
            ],
        ]);
    }
}
