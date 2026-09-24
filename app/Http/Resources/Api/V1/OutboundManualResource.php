<?php

namespace App\Http\Resources\Api\V1;

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
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
