<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('orders.manage', true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
require_valid_csrf(true);

$input = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
$orderId = (int)($input['order_id'] ?? 0);

$pdo = Database::getConnection();
$stmt = $pdo->prepare(
    'SELECT o.*, s.provider_id AS svc_provider_id, s.provider_service_id
     FROM orders o
     INNER JOIN services s ON s.id = o.service_id
     WHERE o.id = ? LIMIT 1'
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    json_response(['ok' => false, 'error' => 'Order not found.'], 404);
}
if (!empty($order['provider_order_id'])) {
    json_response(['ok' => false, 'error' => 'Order already dispatched (Provider Order #' . $order['provider_order_id'] . ').'], 400);
}

$providerId = (int)($order['provider_id'] ?: $order['svc_provider_id'] ?: 0);
if ($providerId <= 0 || empty($order['provider_service_id'])) {
    json_response(['ok' => false, 'error' => 'Order service is not linked to a provider service ID.'], 400);
}

$provider = Provider::find($providerId);
if (!$provider) {
    json_response(['ok' => false, 'error' => 'Provider not found.'], 404);
}

$apiParams = [
    'service'  => $order['provider_service_id'],
    'link'     => $order['link'],
    'quantity' => (int)$order['quantity'],
];
if (!empty($order['custom_comments'])) {
    $apiParams['comments'] = $order['custom_comments'];
}

$res = Provider::request($provider, 'add', $apiParams);
if (!$res['ok'] || empty($res['data']['order'])) {
    json_response(['ok' => false, 'error' => $res['error'] ?? 'Provider rejected order dispatch.'], 400);
}

$extId = (string)$res['data']['order'];
$pdo->prepare('UPDATE orders SET provider_id = ?, provider_order_id = ?, status = "processing", provider_response = ? WHERE id = ?')
    ->execute([$providerId, $extId, json_encode($res['data']), $orderId]);
Order::addLog($orderId, $order['status'], 'processing', "Manually dispatched to provider {$provider['name']} (Ext ID: {$extId})", 'admin', (int)$admin['id']);

json_response(['ok' => true, 'provider_order_id' => $extId]);
