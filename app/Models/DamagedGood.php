<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamagedGood extends Model
{
    use HasFactory;

    public const SOURCE_WAREHOUSE = 'warehouse';
    public const SOURCE_INBOUND_RETURN = 'inbound_return';
    public const SOURCE_CUSTOMER_RETURN = 'customer_return';
    public const SOURCE_TRANSFER_REJECT = 'transfer_reject';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_LEGACY_DISPLAY = 'display';

    protected $fillable = [
        'code',
        'source_type',
        'source_warehouse_id',
        'source_ref',
        'transacted_at',
        'note',
        'status',
        'approved_at',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(DamagedGoodItem::class, 'damaged_good_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sourceWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function allocationSourceItems()
    {
        return $this->hasManyThrough(
            DamagedAllocationItem::class,
            DamagedGoodItem::class,
            'damaged_good_id',
            'damaged_good_item_id',
            'id',
            'id'
        );
    }

    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_WAREHOUSE => 'Stok Gudang',
            self::SOURCE_INBOUND_RETURN => 'Retur Inbound',
            self::SOURCE_CUSTOMER_RETURN => 'Retur Customer',
            self::SOURCE_TRANSFER_REJECT => 'Reject Transfer Gudang',
            self::SOURCE_MANUAL => 'Manual',
            self::SOURCE_LEGACY_DISPLAY => 'Display (Legacy)',
        ];
    }

    public static function creatableSourceLabels(): array
    {
        return [
            self::SOURCE_WAREHOUSE => self::sourceLabels()[self::SOURCE_WAREHOUSE],
            self::SOURCE_INBOUND_RETURN => self::sourceLabels()[self::SOURCE_INBOUND_RETURN],
            self::SOURCE_MANUAL => self::sourceLabels()[self::SOURCE_MANUAL],
        ];
    }

    public static function creatableSourceTypes(): array
    {
        return array_keys(self::creatableSourceLabels());
    }

    public static function sourceLabelFor(?string $sourceType): string
    {
        if (!$sourceType) {
            return '-';
        }

        return self::sourceLabels()[$sourceType] ?? $sourceType;
    }
}
