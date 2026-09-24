<?php

return [
    'enabled' => (bool) env('OUTBOUND_MANUAL_API_ENABLED', false),
    'token' => env('OUTBOUND_MANUAL_API_TOKEN'),
    'rate_limit_per_minute' => (int) env('OUTBOUND_MANUAL_API_RATE_LIMIT_PER_MINUTE', 30),
    'created_by_user_id' => env('OUTBOUND_MANUAL_API_CREATED_BY_USER_ID'),
];
