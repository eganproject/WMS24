<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Backup
    |--------------------------------------------------------------------------
    |
    | Used by the `db:backup` command, which the scheduler runs every night.
    | The dump uses --single-transaction so tables are not locked, and runs
    | with the lowest CPU/disk priority so other apps on the server are not
    | slowed down. Files are gzip-compressed and stored outside the public
    | folder.
    |
    */

    'enabled' => env('DB_BACKUP_ENABLED', true),

    // Time of day the scheduler runs the backup (server timezone).
    'schedule_time' => env('DB_BACKUP_TIME', '01:00'),

    // Folder where the .sql.gz files are written.
    'path' => env('DB_BACKUP_PATH', storage_path('app/private/backups/database')),

    // Backups older than this many days are deleted after a successful run.
    'keep_days' => (int) env('DB_BACKUP_KEEP_DAYS', 14),

    // Path to the mysqldump binary (leave as is if it is on the PATH).
    'mysqldump_binary' => env('DB_BACKUP_MYSQLDUMP', 'mysqldump'),

    // Run the dump with nice/ionice so it gets the lowest priority.
    'low_priority' => env('DB_BACKUP_LOW_PRIORITY', true),

    // Maximum run time in seconds before the dump is stopped.
    'timeout' => (int) env('DB_BACKUP_TIMEOUT', 3600),

];
