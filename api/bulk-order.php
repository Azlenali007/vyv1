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

$apiKey = trim((string)($input['key'] ?? ''));
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
    require_valid_csrf(true);
}

$items = $input['orders'] ?? [];
if (!is_array($items) || empty($items)) {
    json_response(['ok' => false, 'error' => 'Orders array is required.'], 400);
}
if (count($items) > 100) {
    json_response(['ok' => false, 'error' => 'Maximum 100 orders per bulk request.'], 400);
}

$results = [];
foreach ($items as $idx => $item) {
    $res = Order::place(
        (int)$user['id'],
        (int)($item['service_id'] ?? 0),
        (string)($item['link'] ?? ''),
        (int)($item['quantity'] ?? 0),
        $item['comments'] ?? null
    );
    $results[] = array_merge(['index' => $idx], $res);
}

json_response(['ok' => true, 'results' => $results]);
