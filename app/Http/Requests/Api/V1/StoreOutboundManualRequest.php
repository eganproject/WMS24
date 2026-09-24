<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreOutboundManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                $item['sku'] = trim((string) ($item['sku'] ?? ''));

                return $item;
            })
            ->all();

        $this->merge([
            'external_id' => trim((string) $this->input('external_id', '')),
            'warehouse_code' => trim((string) $this->input('warehouse_code', '')),
            'items' => $items,
        ]);
    }

    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:100'],
            'warehouse_code' => ['required', 'string', 'max:50', 'exists:warehouses,code'],
            'transacted_at' => ['required', 'date'],
            'ref_no' => ['nullable', 'string', 'max:100'],
            'surat_jalan_no' => ['nullable', 'string', 'max:100'],
            'surat_jalan_at' => ['nullable', 'date'],
            'recipient_name' => ['nullable', 'string', 'max:150'],
            'recipient_phone' => ['nullable', 'string', 'max:50'],
            'recipient_address' => ['nullable', 'string', 'max:1000'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.sku' => ['required', 'string', 'max:100', 'distinct', 'exists:items,sku'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.koli' => ['nullable', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'external_id.required' => 'external_id wajib diisi sebagai kunci idempotensi.',
            'warehouse_code.exists' => 'Kode gudang tidak ditemukan.',
            'transacted_at.date' => 'transacted_at harus berupa tanggal/waktu yang valid.',
            'items.max' => 'Maksimal 500 baris item dalam satu request.',
            'items.*.sku.distinct' => 'SKU tidak boleh duplikat dalam satu outbound.',
            'items.*.sku.exists' => 'SKU tidak ditemukan di master item.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Data outbound manual tidak valid.',
                'details' => $validator->errors()->toArray(),
            ],
        ], 422));
    }
}
