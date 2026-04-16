<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function inboundTransactions()
    {
        return $this->hasMany(InboundTransaction::class, 'supplier_id');
    }

    public function outboundTransactions()
    {
        return $this->hasMany(OutboundTransaction::class, 'supplier_id');
    }
}
