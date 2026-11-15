<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$apiKey = trim((string)($_REQUEST['key'] ?? ''));
$pdo = Database::getConnection();

if ($apiKey !== '') {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE api_key = ? AND api_status = 1 AND status = "active" LIMIT 1');
    $stmt->execute([$apiKey]);
    $user = $stmt->fetch() ?: null;
    if (!$user) {
        json_response(['ok' => false, 'error' => 'Invalid API key.'], 401);
    }
} else {
    $user = require_user_login(true);
}

$orderId = (int)($_REQUEST['order_id'] ?? $_REQUEST['order'] ?? 0);
if ($orderId <= 0) {
    json_response(['ok' => false, 'error' => 'Valid order_id is required.'], 400);
}

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$orderId, (int)$user['id']]);
$order = $stmt->fetch();

if (!$order) {
    json_response(['ok' => false, 'error' => 'Order not found.'], 404);
}

$syncWarning = null;
if (!empty($order['provider_id']) && !empty($order['provider_order_id']) && !in_array($order['status'], ['completed', 'cancelled', 'refunded'], true)) {
    $sync = Order::syncStatusFromProvider($orderId);
    if (!$sync['ok']) {
        $syncWarning = $sync['error'];
    } else {
        $stmt->execute([$orderId, (int)$user['id']]);
        $order = $stmt->fetch();
    }
}

json_response([
    'ok'          => true,
    'order_id'    => (int)$order['id'],
    'order_code'  => $order['order_code'],
    'status'      => $order['status'],
    'start_count' => (int)$order['start_count'],
    'remains'     => (int)$order['remains'],
    'charge'      => (float)$order['charge'],
    'sync_warning'=> $syncWarning,
]);
