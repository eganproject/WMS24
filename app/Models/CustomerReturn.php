<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerReturn extends Model
{
    use HasFactory;

    public const STATUS_INSPECTED = 'inspected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NO_RECEIVED = 'no_received';

    protected $fillable = [
        'code',
        'resi_id',
        'damaged_good_id',
        'resi_no',
        'order_ref',
        'received_at',
        'inspected_at',
        'finalized_at',
        'status',
        'note',
        'item_image_path',
        'created_by',
        'inspected_by',
        'finalized_by',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'inspected_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(CustomerReturnItem::class, 'customer_return_id');
    }

    public function resi()
    {
        return $this->belongsTo(Resi::class, 'resi_id');
    }

    public function damagedGood()
    {
        return $this->belongsTo(DamagedGood::class, 'damaged_good_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isCompleted(): bool
    {
        return in_array((string) $this->status, [self::STATUS_COMPLETED, self::STATUS_NO_RECEIVED], true);
    }

    public function hasStockMutation(): bool
    {
        return (string) $this->status === self::STATUS_COMPLETED;
    }

    public function statusLabel(): string
    {
        return match ((string) $this->status) {
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_NO_RECEIVED => 'Tidak Diterima',
            default => 'Belum Finalisasi',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ((string) $this->status) {
            self::STATUS_COMPLETED => 'success',
            self::STATUS_NO_RECEIVED => 'secondary',
            default => 'warning',
        };
    }
}
