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

$res = Order::syncStatusFromProvider($orderId);
json_response($res, $res['ok'] ? 200 : 400);
