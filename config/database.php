<?php
declare(strict_types=1);

return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_NAME') ?: 'smm_panel',
    'username' => getenv('DB_USER') ?: 'smm_user',
    'password' => getenv('DB_PASS') ?: 'smm_pass_2026',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
