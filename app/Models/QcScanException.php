<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QcScanException extends Model
{
    use HasFactory;

    protected $table = 'qc_scan_exceptions';

    protected $fillable = [
        'sku',
        'note',
    ];
}
