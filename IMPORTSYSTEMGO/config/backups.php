<?php

return [
    'disk' => 'local',
    'directory' => 'backups',
    'mysql_dump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql_dump_timeout_seconds' => (int) env('BACKUP_DUMP_TIMEOUT', 900),
    'maximum_archive_bytes' => (int) env('BACKUP_MAX_BYTES', 536870912),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    'minimum_interval_minutes' => (int) env('BACKUP_MIN_INTERVAL_MINUTES', 15),
];
