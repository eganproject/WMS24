<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DamagedGood;
use App\Models\DamagedAllocation;
use App\Models\InboundTransaction;
use App\Models\OutboundTransaction;
use App\Models\StockMutation;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StockMutationController extends Controller
{
    public function index()
    {
        $warehouseId = WarehouseService::defaultWarehouseId();
        $warehouseLabel = Warehouse::where('id', $warehouseId)->value('name') ?? 'Gudang Besar';
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name', 'code']);

        return view('admin.inventory.stock-mutations.index', [
            'warehouses' => $warehouses,
            'defaultWarehouseId' => $warehouseId,
            'displayWarehouseId' => WarehouseService::displayWarehouseId(),
            'damagedWarehouseId' => WarehouseService::damagedWarehouseId(),
            'warehouseLabel' => $warehouseLabel,
        ]);
    }

    public function data(Request $request)
    {
        $query = StockMutation::query()
            ->with(['item', 'referenceItem', 'creator', 'warehouse'])
            ->orderBy('occurred_at', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('source_code', 'like', "%{$search}%")
                    ->orWhere('source_type', 'like', "%{$search}%")
                    ->orWhere('source_subtype', 'like', "%{$search}%")
                    ->orWhereHas('creator', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })
                    ->orWhere('reference_sku', 'like', "%{$search}%")
                    ->orWhereHas('warehouse', function ($whQ) use ($search) {
                        $whQ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilter($query, $request);

        $warehouseFilter = $request->input('warehouse_id');
        if ($warehouseFilter === null || $warehouseFilter === '') {
            $warehouseFilter = WarehouseService::defaultWarehouseId();
        }
        if ($warehouseFilter !== 'all') {
            $query->where('warehouse_id', (int) $warehouseFilter);
        }

        $recordsTotal = StockMutation::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($m) {
            $itemLabel = $this->formatMutationItemLabel($m);
            $ts = $m->occurred_at ? Carbon::parse($m->occurred_at)->format('Y-m-d H:i') : '';
            $direction = $m->direction === 'in' ? 'IN' : 'OUT';
            $source = strtoupper($m->source_type ?? '').($m->source_subtype ? ' / '.$m->source_subtype : '');
            return [
                'id' => $m->id,
                'occurred_at' => $ts,
                'item' => $itemLabel,
                'warehouse' => $m->warehouse?->name ?? '-',
                'warehouse_id' => $m->warehouse_id,
                'user' => $m->creator?->name ?? '-',
                'direction' => $direction,
                'qty' => (int) $m->qty,
                'source' => trim($source),
                'source_code' => $m->source_code ?? '',
                'note' => $m->note ?? '',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where('occurred_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('occurred_at', '<=', $to);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    public function show(int $id)
    {
        $mutation = StockMutation::with(['item', 'referenceItem', 'creator', 'warehouse'])->findOrFail($id);
        [$sourceSummary, $sourceItems] = $this->resolveSource($mutation);

        $itemLabel = $this->formatMutationItemLabel($mutation);
        $direction = $mutation->direction === 'in' ? 'IN' : 'OUT';
        $source = strtoupper($mutation->source_type ?? '').($mutation->source_subtype ? ' / '.$mutation->source_subtype : '');

        return response()->json([
            'mutation' => [
                'id' => $mutation->id,
                'occurred_at' => $mutation->occurred_at?->format('Y-m-d H:i'),
                'item' => $itemLabel,
                'warehouse' => $mutation->warehouse?->name ?? '-',
                'warehouse_id' => $mutation->warehouse_id,
                'direction' => $direction,
                'qty' => (int) $mutation->qty,
                'source' => trim($source),
                'source_code' => $mutation->source_code ?? '',
                'note' => $mutation->note ?? '',
                'user' => $mutation->creator?->name ?? '-',
            ],
            'source' => $sourceSummary ? array_merge($sourceSummary, ['items' => $sourceItems]) : null,
        ]);
    }

    private function resolveSource(StockMutation $mutation): array
    {
        $sourceSummary = null;
        $sourceItems = [];

        switch ($mutation->source_type) {
            case 'inbound':
                $tx = InboundTransaction::with('items.item')->find($mutation->source_id);
                if ($tx) {
                    $sourceSummary = [
                        'label' => 'Inbound / '.$tx->type,
                        'code' => $tx->code,
                        'ref' => $tx->ref_no ?? '-',
                        'date' => $tx->transacted_at?->format('Y-m-d H:i'),
                        'note' => $tx->note ?? '-',
                    ];
                    $sourceItems = $tx->items->map(function ($row) {
                        return [
                            'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                            'qty' => (int) $row->qty,
                            'note' => $row->note ?? '-',
                        ];
                    })->values()->all();
                }
                break;
            case 'outbound':
                $tx = OutboundTransaction::with('items.item')->find($mutation->source_id);
                if ($tx) {
                    $sourceSummary = [
                        'label' => 'Outbound / '.$tx->type,
                        'code' => $tx->code,
                        'ref' => $tx->ref_no ?? '-',
                        'date' => $tx->transacted_at?->format('Y-m-d H:i'),
                        'note' => $tx->note ?? '-',
                    ];
                    $sourceItems = $tx->items->map(function ($row) {
                        return [
                            'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                            'qty' => (int) $row->qty,
                            'note' => $row->note ?? '-',
                        ];
                    })->values()->all();
                }
                break;
            case 'opname':
                $opname = StockOpname::with('items.item')->find($mutation->source_id);
                if ($opname) {
                    $sourceSummary = [
                        'label' => 'Stock Opname',
                        'code' => $opname->code,
                        'ref' => '-',
                        'date' => $opname->transacted_at?->format('Y-m-d H:i'),
                        'note' => $opname->note ?? '-',
                    ];
                    $sourceItems = $opname->items->map(function ($row) {
                        return [
                            'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                            'qty' => (int) $row->adjustment,
                            'note' => $row->note ?? '-',
                            'meta' => 'System '.$row->system_qty.', Counted '.$row->counted_qty.', Adj '.$row->adjustment,
                        ];
                    })->values()->all();
                }
                break;
            case 'adjustment':
                $adjustment = \App\Models\StockAdjustment::with('items.item')->find($mutation->source_id);
                if ($adjustment) {
                    $sourceSummary = [
                        'label' => 'Penyesuaian Stok',
                        'code' => $adjustment->code,
                        'ref' => '-',
                        'date' => $adjustment->transacted_at?->format('Y-m-d H:i'),
                        'note' => $adjustment->note ?? '-',
                    ];
                    $sourceItems = $adjustment->items->map(function ($row) {
                        $dir = $row->direction === 'in' ? 'IN' : 'OUT';
                        return [
                            'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')).' ('.$dir.')',
                            'qty' => (int) $row->qty,
                            'note' => $row->note ?? '-',
                        ];
                    })->values()->all();
                }
                break;
            case 'damaged':
                $damage = DamagedGood::with(['items.item', 'sourceWarehouse'])->find($mutation->source_id);
                if ($damage) {
                    $sourceWarehouse = $damage->sourceWarehouse?->name ?? '-';
                    $damagedWarehouse = Warehouse::where('id', WarehouseService::damagedWarehouseId())->value('name') ?? 'Gudang Rusak';
                    $ref = $damage->source_ref
                        ? $damage->source_ref.' | Gudang Asal: '.$sourceWarehouse
                        : 'Gudang Asal: '.$sourceWarehouse;
                    $note = $damage->note
                        ? $damage->note.' | Gudang Rusak: '.$damagedWarehouse
                        : 'Gudang Rusak: '.$damagedWarehouse;
                    $sourceSummary = [
                        'label' => 'Intake Barang Rusak / '.$damage->source_type,
                        'code' => $damage->code,
                        'ref' => $ref,
                        'date' => $damage->transacted_at?->format('Y-m-d H:i'),
                        'note' => $note,
                    ];
                    $sourceItems = $damage->items->map(function ($row) {
                        return [
                            'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                            'qty' => (int) $row->qty,
                            'note' => $row->note ?? '-',
                        ];
                    })->values()->all();
                }
                break;
            case 'damaged_allocation':
                $allocation = DamagedAllocation::with([
                    'sourceItems.item',
                    'sourceItems.damagedGoodItem.damagedGood',
                    'outputItems.item',
                    'supplier',
                    'targetWarehouse',
                    'recipe',
                ])->find($mutation->source_id);
                if ($allocation) {
                    $typeLabel = match ($allocation->type) {
                        'return_supplier' => 'Retur Supplier',
                        'disposal' => 'Disposal',
                        'rework' => 'Rework SKU',
                        default => $allocation->type,
                    };
                    $refParts = [];
                    if ($allocation->source_ref) {
                        $refParts[] = $allocation->source_ref;
                    }
                    if ($allocation->type === 'return_supplier' && $allocation->supplier) {
                        $refParts[] = 'Supplier: '.$allocation->supplier->name;
                    }
                    if ($allocation->type === 'rework' && $allocation->targetWarehouse) {
                        $refParts[] = 'Gudang Hasil: '.$allocation->targetWarehouse->name;
                    }
                    if ($allocation->type === 'rework' && $allocation->recipe) {
                        $refParts[] = 'Resep: '.$allocation->recipe->code;
                        if ((int) ($allocation->recipe_multiplier ?? 0) > 0) {
                            $refParts[] = 'Batch: '.$allocation->recipe_multiplier;
                        }
                    }
                    $sourceSummary = [
                        'label' => 'Alokasi Barang Rusak / '.$typeLabel,
                        'code' => $allocation->code,
                        'ref' => !empty($refParts) ? implode(' | ', $refParts) : '-',
                        'date' => $allocation->transacted_at?->format('Y-m-d H:i'),
                        'note' => $allocation->note ?? '-',
                    ];
                    $sourceItems = collect()
                        ->merge($allocation->sourceItems->map(function ($row) {
                            return [
                                'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                                'qty' => (int) $row->qty,
                                'note' => $row->note ?? '-',
                                'meta' => 'Sumber rusak dari intake '.($row->damagedGoodItem?->damagedGood?->code ?? '-'),
                            ];
                        }))
                        ->merge($allocation->outputItems->map(function ($row) use ($allocation) {
                            $target = $allocation->targetWarehouse?->name ?? '-';
                            return [
                                'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                                'qty' => (int) $row->qty,
                                'note' => $row->note ?? '-',
                                'meta' => 'Hasil rework ke gudang '.$target,
                            ];
                        }))
                        ->values()
                        ->all();
                }
                break;
            case 'transfer':
                $transfer = StockTransfer::with(['items.item', 'fromWarehouse', 'toWarehouse'])->find($mutation->source_id);
                if ($transfer) {
                    $from = $transfer->fromWarehouse?->name ?? '-';
                    $to = $transfer->toWarehouse?->name ?? '-';
                    $sourceSummary = [
                        'label' => "Transfer Gudang ({$from} -> {$to})",
                        'code' => $transfer->code,
                        'ref' => '-',
                        'date' => $transfer->transacted_at?->format('Y-m-d H:i'),
                        'note' => $transfer->note ?? '-',
                    ];
                    $sourceItems = $transfer->items->map(function ($row) {
                        return [
                            'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                            'qty' => (int) $row->qty,
                            'note' => $row->note ?? '-',
                        ];
                    })->values()->all();
                }
                break;
            case 'qc_shipment':
                $qc = \App\Models\QcResiScan::with(['items', 'resi'])->find($mutation->source_id);
                if ($qc) {
                    $sourceSummary = [
                        'label' => 'QC Pengiriman',
                        'code' => $qc->scan_code ?: ($qc->resi?->no_resi ?? '-'),
                        'ref' => $qc->resi?->id_pesanan ?? '-',
                        'date' => $qc->completed_at?->format('Y-m-d H:i'),
                        'note' => 'Stok dikunci keluar saat QC selesai',
                    ];
                    $sourceItems = $qc->items->map(function ($row) {
                        return [
                            'label' => (string) ($row->sku ?? '-'),
                            'qty' => (int) $row->scanned_qty,
                            'note' => '-',
                        ];
                    })->values()->all();
                }
                break;
        }

        return [$sourceSummary, $sourceItems];
    }

    private function formatMutationItemLabel(StockMutation $mutation): string
    {
        $itemSku = trim((string) ($mutation->item?->sku ?? ''));
        $itemName = trim((string) ($mutation->item?->name ?? ''));
        $baseLabel = trim($itemSku.' - '.$itemName, ' -');

        $referenceSku = trim((string) ($mutation->reference_sku ?? ''));
        if ($referenceSku === '' || strcasecmp($referenceSku, $itemSku) === 0) {
            return $baseLabel !== '' ? $baseLabel : '-';
        }

        return trim($baseLabel !== '' ? $baseLabel.' | Ref Bundle: '.$referenceSku : 'Ref Bundle: '.$referenceSku);
    }
}
