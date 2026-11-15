<?php
declare(strict_types=1);

/**
 * Router for PHP built-in development server (php -S 0.0.0.0:3000 router.php).
 * Mirrors .htaccess security rules and serves static assets or PHP entrypoints cleanly.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$docRoot = __DIR__;

// Block access to internal directories and sensitive extensions
$blockedPrefixes = ['/config/', '/classes/', '/database/', '/cron/', '/includes/', '/.'];
foreach ($blockedPrefixes as $prefix) {
    if (str_starts_with($uri, $prefix)) {
        http_response_code(403);
        echo '403 Forbidden';
        exit;
    }
}

if (preg_match('/\.(sql|env|md|log|sh|json|lock)$/i', $uri)) {
    http_response_code(403);
    echo '403 Forbidden';
    exit;
}

if (preg_match('#^/uploads/.*\.php$#i', $uri)) {
    http_response_code(403);
    echo '403 Forbidden';
    exit;
}

$fullPath = realpath($docRoot . $uri);

// Prevent path traversal outside document root
if ($fullPath !== false && str_starts_with($fullPath, realpath($docRoot))) {
    if (is_file($fullPath)) {
        // Let PHP serve static assets directly
        if (!str_ends_with(strtolower($fullPath), '.php')) {
            return false;
        }
        require $fullPath;
        exit;
    }
    if (is_dir($fullPath)) {
        $dirIndex = rtrim($fullPath, '/') . '/index.php';
        if (is_file($dirIndex)) {
            require $dirIndex;
            exit;
        }
    }
}

// Extensionless fallback (e.g., /login -> /login.php)
$cleanUri = rtrim($uri, '/');
if ($cleanUri === '') {
    require $docRoot . '/index.php';
    exit;
}

$phpCandidate = realpath($docRoot . $cleanUri . '.php');
if ($phpCandidate !== false && str_starts_with($phpCandidate, realpath($docRoot)) && is_file($phpCandidate)) {
    require $phpCandidate;
    exit;
}

http_response_code(404);
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>404 Not Found</title></head><body style="font-family:sans-serif;padding:3rem;text-align:center;"><h1>404 - Page Not Found</h1><p><a href="/">Return Home</a></p></body></html>';
