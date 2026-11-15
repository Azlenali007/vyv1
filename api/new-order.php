<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$apiKey = trim((string)($input['key'] ?? $_REQUEST['key'] ?? ''));
$user = null;

if ($apiKey !== '') {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE api_key = ? AND api_status = 1 AND status = "active" LIMIT 1');
    $stmt->execute([$apiKey]);
    $user = $stmt->fetch() ?: null;
    if (!$user) {
        json_response(['ok' => false, 'error' => 'Invalid or disabled API key.'], 401);
    }
} else {
    $user = require_user_login(true);
    require_valid_csrf(true);
}

$res = Order::place(
    (int)$user['id'],
    (int)($input['service_id'] ?? $input['service'] ?? 0),
    (string)($input['link'] ?? ''),
    (int)($input['quantity'] ?? 0),
    $input['comments'] ?? null,
    $input['idempotency_key'] ?? null
);

if ($apiKey !== '') {
    Provider::logApiCall(
        null,
        (int)$user['id'],
        'inbound_user',
        '/api/new-order.php',
        'new_order',
        json_encode(['service_id' => $input['service_id'] ?? $input['service'] ?? 0, 'quantity' => $input['quantity'] ?? 0]),
        json_encode($res),
        $res['ok'] ? 200 : 400,
        null,
        (bool)$res['ok'],
        $res['error'] ?? null
    );
}

json_response($res, $res['ok'] ? 200 : 400);
