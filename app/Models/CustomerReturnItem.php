<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerReturnItem extends Model
{
    use HasFactory;

    public const ROOT_CAUSE_WRONG_ITEM = 'wrong_item';
    public const ROOT_CAUSE_DAMAGED_PACKING = 'damaged_packing';
    public const ROOT_CAUSE_DAMAGED_COURIER = 'damaged_courier';
    public const ROOT_CAUSE_PRODUCT_DEFECT = 'product_defect';
    public const ROOT_CAUSE_BUYER_ISSUE = 'buyer_issue';
    public const ROOT_CAUSE_INCOMPLETE_ITEM = 'incomplete_item';
    public const ROOT_CAUSE_OTHER = 'other';

    protected $fillable = [
        'customer_return_id',
        'item_id',
        'expected_qty',
        'received_qty',
        'good_qty',
        'packaging_damaged_qty',
        'damaged_qty',
        'root_cause',
        'note',
    ];

    protected $casts = [
        'expected_qty' => 'integer',
        'received_qty' => 'integer',
        'good_qty' => 'integer',
        'packaging_damaged_qty' => 'integer',
        'damaged_qty' => 'integer',
    ];

    public function customerReturn()
    {
        return $this->belongsTo(CustomerReturn::class, 'customer_return_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public static function rootCauseLabels(): array
    {
        return [
            self::ROOT_CAUSE_WRONG_ITEM => 'Salah Kirim',
            self::ROOT_CAUSE_DAMAGED_PACKING => 'Rusak Packing',
            self::ROOT_CAUSE_DAMAGED_COURIER => 'Rusak Ekspedisi',
            self::ROOT_CAUSE_PRODUCT_DEFECT => 'Produk Cacat',
            self::ROOT_CAUSE_BUYER_ISSUE => 'Buyer Issue',
            self::ROOT_CAUSE_INCOMPLETE_ITEM => 'Barang Tidak Lengkap',
            self::ROOT_CAUSE_OTHER => 'Lainnya',
        ];
    }

    public static function rootCauseLabelFor(?string $rootCause): string
    {
        if (!$rootCause) {
            return '-';
        }

        return self::rootCauseLabels()[$rootCause] ?? $rootCause;
    }

    public function rootCauseLabel(): string
    {
        return self::rootCauseLabelFor($this->root_cause);
    }
}
