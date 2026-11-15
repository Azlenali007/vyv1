<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('providers.manage', true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
require_valid_csrf(true);

$input = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
$providerId = (int)($input['provider_id'] ?? 0);

$res = Provider::testConnection($providerId);
log_activity('admin', (int)$admin['id'], 'provider.test', 'provider', $providerId, $res['ok'] ? 'Connection verified' : ($res['error'] ?? 'Failed'));

json_response($res, $res['ok'] ? 200 : 400);
