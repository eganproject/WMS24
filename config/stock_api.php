<?php

return [
    'enabled' => (bool) env('STOCK_API_ENABLED', true),
    'warehouse_code' => env('STOCK_API_WAREHOUSE_CODE', 'WMS24'),
    'source_warehouse_code' => env('STOCK_API_SOURCE_WAREHOUSE_CODE', 'GUDANG_BESAR'),
    'token' => env('STOCK_API_TOKEN'),
    'rate_limit_per_minute' => (int) env('STOCK_API_RATE_LIMIT_PER_MINUTE', 60),
];
