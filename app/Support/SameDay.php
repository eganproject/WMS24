<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Index-friendly replacement for whereDate().
 *
 * whereDate() wraps the column in DATE(), so MySQL cannot use an index and
 * scans the whole table. Inside a lockForUpdate() that scan locks every row.
 * A half-open range on the raw column gives the same result and uses the index.
 */
class SameDay
{
    public static function apply($query, string $column, string $date)
    {
        $day = Carbon::parse($date)->startOfDay();

        return $query
            ->where($column, '>=', $day->toDateString())
            ->where($column, '<', $day->copy()->addDay()->toDateString());
    }
}
