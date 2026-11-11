<?php

namespace App\Http\Resources\Api\V1;

use App\Support\OutboundManualStockPlanner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutboundManualResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->api_external_id,
            'code' => $this->code,
            'type' => $this->type,
            'status' => $this->status,
            'ref_no' => $this->ref_no,
            'transacted_at' => $this->transacted_at?->toIso8601String(),
            'surat_jalan_no' => $this->surat_jalan_no,
            'surat_jalan_at' => $this->surat_jalan_at?->toIso8601String(),
            'recipient' => [
                'name' => $this->recipient_name,
                'phone' => $this->recipient_phone,
                'address' => $this->recipient_address,
            ],
            'ekspedisi' => $this->ekspedisi,
            'note' => $this->note,
            'warehouse' => [
                'code' => $this->warehouse?->code,
                'name' => $this->warehouse?->name,
            ],
            'created_by' => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null,
            'items' => $this->items->map(fn ($row) => [
                'sku' => $row->item?->sku,
                'name' => $row->item?->name,
                'qty' => (int) $row->qty,
                'note' => $row->note,
            ])->values(),
            'replenishment' => $this->replenishment(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** Info kekurangan stok Gudang Display; null bila order bukan dari Display atau sudah selesai. */
    private function replenishment(): ?array
    {
        $status = OutboundManualStockPlanner::statusFor([$this->resource])[(int) $this->id] ?? null;
        if (! $status) {
            return null;
        }

        return [
            'state' => $status['state'],
            'items' => collect($status['items'])->map(fn (array $line) => [
                'sku' => $line['sku'],
                'required_qty' => $line['required_qty'],
                'display_available_qty' => $line['display_available_qty'],
                'shortfall_qty' => $line['shortfall_qty'],
                'recommended_koli' => $line['recommended_koli'],
                'recommended_transfer_qty' => $line['recommended_transfer_qty'],
                'covered_by_earlier_order' => $line['covered_by_earlier_order'],
                'state' => $line['state'],
            ])->values()->all(),
        ];
    }
}
