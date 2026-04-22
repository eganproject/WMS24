<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerReturn extends Model
{
    use HasFactory;

    public const STATUS_INSPECTED = 'inspected';
    public const STATUS_COMPLETED = 'completed';

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
        return (string) $this->status === self::STATUS_COMPLETED;
    }
}
