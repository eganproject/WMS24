<?php

namespace App\Services;

use App\Exceptions\OutboundManualApiException;
use App\Models\Item;
use App\Models\OutboundItem;
use App\Models\OutboundTransaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\BundleService;
use App\Support\OutboundKoliExpectation;
use App\Support\OutboundManualQcStatus;
use App\Support\StockService;
use App\Support\WarehouseService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OutboundManualApiService
{
    /**
     * @return array{transaction: OutboundTransaction, replayed: bool}
     */
    public function create(array $payload): array
    {
        $normalized = $this->normalize($payload);
        $requestHash = $this->requestHash($normalized);
        $existing = $this->findExisting($normalized['external_id']);

        if ($existing) {
            return $this->replay($existing, $requestHash);
        }

        $creatorId = $this->resolveCreatorId();

        try {
            return DB::transaction(function () use ($normalized, $requestHash, $creatorId) {
                $existing = OutboundTransaction::query()
                    ->where('api_external_id', $normalized['external_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $this->replay($existing, $requestHash);
                }

                StockService::assertSellableAvailable($normalized['items'], $normalized['warehouse']->id);

                $transaction = OutboundTransaction::create([
                    'code' => $this->generateCode('OUT-MNL'),
                    'api_external_id' => $normalized['external_id'],
                    'api_request_hash' => $requestHash,
                    'type' => 'manual',
                    'ref_no' => $normalized['ref_no'],
                    'supplier_id' => null,
                    'recipient_name' => $normalized['recipient_name'],
                    'recipient_phone' => $normalized['recipient_phone'],
                    'recipient_address' => $normalized['recipient_address'],
                    'surat_jalan_no' => $normalized['surat_jalan_no']
                        ?: $this->generateCode('SJ-OUT-MNL'),
                    'surat_jalan_at' => $normalized['surat_jalan_at'],
                    'note' => $normalized['note'],
                    'warehouse_id' => $normalized['warehouse']->id,
                    'transacted_at' => $normalized['transacted_at'],
                    'created_by' => $creatorId,
                    'status' => OutboundManualQcStatus::PENDING_QC,
                ]);

                foreach ($normalized['items'] as $row) {
                    OutboundItem::create([
                        'outbound_transaction_id' => $transaction->id,
                        'item_id' => $row['item_id'],
                        'qty' => $row['qty'],
                        'note' => $row['note'],
                    ]);
                }

                return [
                    'transaction' => $this->loadResponseRelations($transaction),
                    'replayed' => false,
                ];
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->findExisting($normalized['external_id']);
            if (! $existing) {
                throw $exception;
            }

            return $this->replay($existing, $requestHash);
        }
    }

    private function normalize(array $payload): array
    {
        $warehouse = Warehouse::query()
            ->where('code', $payload['warehouse_code'])
            ->first();

        if (! $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_code' => 'Kode gudang tidak ditemukan.',
            ]);
        }

        $skus = collect($payload['items'])->pluck('sku')->all();
        $itemMap = Item::query()
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy('sku');
        $usesKoli = (string) $warehouse->code === WarehouseService::defaultWarehouseCode();

        $items = collect($payload['items'])
            ->values()
            ->map(function (array $row, int $index) use ($itemMap, $usesKoli) {
                $item = $itemMap->get($row['sku']);
                if (! $item) {
                    throw ValidationException::withMessages([
                        "items.{$index}.sku" => "SKU {$row['sku']} tidak ditemukan di master item.",
                    ]);
                }

                $qty = (int) $row['qty'];
                if ($usesKoli) {
                    if (empty($row['koli'])) {
                        throw ValidationException::withMessages([
                            "items.{$index}.koli" => 'Koli wajib diisi untuk outbound manual dari Gudang Besar.',
                        ]);
                    }

                    try {
                        $qty = OutboundKoliExpectation::resolve($item, $qty, (int) $row['koli'])['qty'];
                    } catch (ValidationException $exception) {
                        $message = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();
                        throw ValidationException::withMessages([
                            "items.{$index}.qty" => $message,
                            "items.{$index}.koli" => $message,
                        ]);
                    }
                }

                if ($item->isBundle()) {
                    BundleService::validateComponents(
                        $item,
                        $item->bundleComponents()->get([
                            'component_item_id as item_id',
                            'required_qty as qty',
                        ])->toArray(),
                    );
                }

                return [
                    'item_id' => (int) $item->id,
                    'sku' => (string) $item->sku,
                    'qty' => $qty,
                    'koli' => isset($row['koli']) ? (int) $row['koli'] : null,
                    'note' => $this->nullableText($row['note'] ?? null),
                ];
            })
            ->all();

        return [
            'external_id' => (string) $payload['external_id'],
            'warehouse' => $warehouse,
            'warehouse_code' => (string) $warehouse->code,
            'transacted_at' => Carbon::parse($payload['transacted_at']),
            'ref_no' => $this->nullableText($payload['ref_no'] ?? null),
            'surat_jalan_no' => $this->nullableText($payload['surat_jalan_no'] ?? null),
            'surat_jalan_at' => empty($payload['surat_jalan_at'])
                ? null
                : Carbon::parse($payload['surat_jalan_at']),
            'recipient_name' => $this->nullableText($payload['recipient_name'] ?? null),
            'recipient_phone' => $this->nullableText($payload['recipient_phone'] ?? null),
            'recipient_address' => $this->nullableText($payload['recipient_address'] ?? null),
            'note' => $this->nullableText($payload['note'] ?? null),
            'items' => $items,
        ];
    }

    private function requestHash(array $payload): string
    {
        $items = collect($payload['items'])
            ->map(fn (array $row) => [
                'sku' => $row['sku'],
                'qty' => $row['qty'],
                'koli' => $row['koli'],
                'note' => $row['note'],
            ])
            ->sortBy('sku')
            ->values()
            ->all();

        return hash('sha256', json_encode([
            'warehouse_code' => $payload['warehouse_code'],
            'transacted_at' => $payload['transacted_at']->toIso8601String(),
            'ref_no' => $payload['ref_no'],
            'surat_jalan_no' => $payload['surat_jalan_no'],
            'surat_jalan_at' => $payload['surat_jalan_at']?->toIso8601String(),
            'recipient_name' => $payload['recipient_name'],
            'recipient_phone' => $payload['recipient_phone'],
            'recipient_address' => $payload['recipient_address'],
            'note' => $payload['note'],
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function replay(OutboundTransaction $transaction, string $requestHash): array
    {
        if (! hash_equals((string) $transaction->api_request_hash, $requestHash)) {
            throw new OutboundManualApiException(
                'IDEMPOTENCY_CONFLICT',
                'external_id sudah digunakan dengan payload yang berbeda.',
                409,
            );
        }

        return [
            'transaction' => $this->loadResponseRelations($transaction),
            'replayed' => true,
        ];
    }

    private function findExisting(string $externalId): ?OutboundTransaction
    {
        return OutboundTransaction::query()
            ->where('api_external_id', $externalId)
            ->first();
    }

    private function loadResponseRelations(OutboundTransaction $transaction): OutboundTransaction
    {
        return $transaction->load([
            'warehouse:id,code,name',
            'creator:id,name',
            'items.item:id,sku,name,koli_qty',
        ]);
    }

    private function resolveCreatorId(): ?int
    {
        $configuredId = (int) config('outbound_manual_api.created_by_user_id', 0);
        if ($configuredId <= 0) {
            return null;
        }

        $exists = User::query()
            ->whereKey($configuredId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw new OutboundManualApiException(
                'API_CONFIGURATION_ERROR',
                'User pembuat transaksi API tidak ditemukan atau tidak aktif.',
                503,
            );
        }

        return $configuredId;
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
