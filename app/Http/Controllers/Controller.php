<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function isExactSearch(Request $request): bool
    {
        return trim(strtolower((string) $request->input('search_mode', ''))) === 'exact';
    }

    protected function applyTextSearch(
        EloquentBuilder|QueryBuilder $query,
        string $column,
        string $search,
        bool $exact,
        string $boolean = 'and'
    ): void {
        if ($exact) {
            $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
            $query->{$method}('LOWER('.$column.') = ?', [mb_strtolower($search)]);

            return;
        }

        $method = $boolean === 'or' ? 'orWhere' : 'where';
        $query->{$method}($column, 'like', "%{$search}%");
    }
}
