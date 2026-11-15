<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$apiKey = trim((string)($_REQUEST['key'] ?? ''));
if ($apiKey !== '') {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE api_key = ? AND api_status = 1 AND status = "active" LIMIT 1');
    $stmt->execute([$apiKey]);
    $user = $stmt->fetch() ?: null;
    if (!$user) {
        json_response(['ok' => false, 'error' => 'Invalid API key.'], 401);
    }
} else {
    $user = require_user_login(true);
}

json_response([
    'ok'       => true,
    'balance'  => (float)$user['balance'],
    'currency' => get_setting('currency_code', DEFAULT_CURRENCY_CODE),
    'symbol'   => get_setting('currency_symbol', DEFAULT_CURRENCY_SYMBOL),
]);
