<?php

return [
    'disk' => 'r2_backups',
    'archive_password' => env('BACKUP_ARCHIVE_PASSWORD'),
    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
    'mysql_path' => env('BACKUP_MYSQL_PATH', 'mysql'),
    'retention' => [
        'daily' => (int) env('BACKUP_RETENTION_DAILY', 30),
        'weekly' => (int) env('BACKUP_RETENTION_WEEKLY', 12),
        'monthly' => (int) env('BACKUP_RETENTION_MONTHLY', 12),
    ],
    'temp_directory' => rtrim(sys_get_temp_dir(), '/\\') . '/clinica-database-backups',
    'queue_connection' => env('BACKUP_QUEUE_CONNECTION', 'database_backups'),
    'queue' => env('BACKUP_QUEUE', 'backups'),
];
