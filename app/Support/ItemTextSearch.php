<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class ItemTextSearch
{
    public static function terms(string $search): array
    {
        $parts = preg_split('/[\r\n,;\t]+/', trim($search)) ?: [];
        $terms = [];

        foreach ($parts as $part) {
            $term = trim($part);
            if ($term === '') {
                continue;
            }

            $key = mb_strtolower($term);
            $terms[$key] = $term;
        }

        return array_slice(array_values($terms), 0, 100);
    }

    public static function apply(
        EloquentBuilder|QueryBuilder $query,
        string $search,
        bool $exact = false,
        array $columns = ['sku', 'name', 'address', 'description']
    ): void {
        $terms = self::terms($search);
        if ($terms === []) {
            return;
        }

        $query->where(function ($outer) use ($terms, $exact, $columns) {
            foreach ($terms as $term) {
                $outer->orWhere(function ($inner) use ($term, $exact, $columns) {
                    foreach ($columns as $index => $column) {
                        if ($exact) {
                            $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                            $inner->{$method}('LOWER('.$column.') = ?', [mb_strtolower($term)]);

                            continue;
                        }

                        $method = $index === 0 ? 'where' : 'orWhere';
                        $inner->{$method}($column, 'like', "%{$term}%");
                    }
                });
            }
        });
    }
}
