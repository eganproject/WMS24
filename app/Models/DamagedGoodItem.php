<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamagedGoodItem extends Model
{
    use HasFactory;

    public const REASON_PHYSICAL_DAMAGE = 'physical_damage';
    public const REASON_FUNCTIONAL_DAMAGE = 'functional_damage';
    public const REASON_PACKAGING_DAMAGE = 'packaging_damage';
    public const REASON_EXPIRED = 'expired';
    public const REASON_MISSING_PART = 'missing_part';
    public const REASON_CUSTOMER_RETURN = 'customer_return';
    public const REASON_OTHER = 'other';

    protected $fillable = [
        'damaged_good_id',
        'item_id',
        'qty',
        'reason_code',
        'note',
    ];

    public function damagedGood()
    {
        return $this->belongsTo(DamagedGood::class, 'damaged_good_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function allocationItems()
    {
        return $this->hasMany(DamagedAllocationItem::class, 'damaged_good_item_id');
    }

    public static function reasonLabels(): array
    {
        return [
            self::REASON_PHYSICAL_DAMAGE => 'Kerusakan Fisik',
            self::REASON_FUNCTIONAL_DAMAGE => 'Gagal Fungsi',
            self::REASON_PACKAGING_DAMAGE => 'Kemasan Rusak',
            self::REASON_EXPIRED => 'Expired',
            self::REASON_MISSING_PART => 'Bagian Tidak Lengkap',
            self::REASON_CUSTOMER_RETURN => 'Retur Customer',
            self::REASON_OTHER => 'Lainnya',
        ];
    }

    public static function reasonLabel(?string $reasonCode): string
    {
        if (!$reasonCode) {
            return self::reasonLabels()[self::REASON_OTHER];
        }

        return self::reasonLabels()[$reasonCode] ?? $reasonCode;
    }
}
