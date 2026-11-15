<?php
declare(strict_types=1);

require_once __DIR__ . '/constants.php';

// Load optional .env file if present
$envFile = BASE_PATH . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val, " \t\n\r\0\x0B\"'");
                if ($key !== '' && getenv($key) === false) {
                    putenv("$key=$val");
                    $_ENV[$key] = $val;
                }
            }
        }
    }
}

// Timezone & error reporting
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Kolkata');

$isDebug = (getenv('APP_DEBUG') === 'true');
ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Secure session initialization
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('SMMPANELSESSID');
    session_start();
}

// Compute base URL dynamically for subdirectory or root hosting
function app_base_url(): string
{
    $configured = getenv('APP_URL');
    if ($configured && $configured !== 'MY_APP_URL') {
        return rtrim($configured, '/');
    }
    if (PHP_SAPI === 'cli') {
        return 'http://localhost:3000';
    }
    $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost:3000');
    return $proto . '://' . $host;
}

// Autoloader for classes/
spl_autoload_register(static function (string $className): void {
    $file = CLASSES_PATH . '/' . basename($className) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// Load essential helpers
require_once __DIR__ . '/database.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/csrf.php';
require_once INCLUDES_PATH . '/validation.php';
require_once INCLUDES_PATH . '/auth.php';
