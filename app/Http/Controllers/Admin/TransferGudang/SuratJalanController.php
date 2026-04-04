<?php

namespace App\Http\Controllers\Admin\TransferGudang;

use App\Http\Controllers\Controller;
use App\Models\TransferRequest;
use Illuminate\Http\Request;

class SuratJalanController extends Controller
{
    public function show(TransferRequest $transferRequest)
    {
        // Ambil shipment terbaru untuk Transfer Request ini dan muat detail transfer (shipment_transfer_details)
        $transferRequest->load(['fromWarehouse', 'toWarehouse']);

        $shipment = $transferRequest->shipments()
            ->with(['itemDetails.item'])
            ->latest('shipping_date')
            ->first();

        if (!$shipment) {
            abort(404, 'Surat jalan belum dibuat untuk permintaan ini.');
        }

        $totalQuantity = $shipment->itemDetails->sum('quantity_shipped');
        $totalKoli = $shipment->itemDetails->sum('koli_shipped');

        return view('admin.transfergudang.surat-jalan.show', compact('transferRequest', 'shipment', 'totalQuantity', 'totalKoli'));
    }
}
