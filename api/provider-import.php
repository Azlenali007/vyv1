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
$mode       = $input['mode'] ?? 'preview';

if ($mode === 'preview') {
    $res = Provider::fetchServices($providerId);
    json_response($res, $res['ok'] ? 200 : 400);
}

$services     = $input['services'] ?? [];
$marginType   = ($input['margin_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage';
$marginValue  = max(0.0, (float)($input['margin_value'] ?? 30));
$categoryId   = !empty($input['target_category_id']) ? (int)$input['target_category_id'] : null;
$updateExists = (bool)($input['update_existing'] ?? true);

if (!is_array($services) || empty($services)) {
    json_response(['ok' => false, 'error' => 'No services provided for import.'], 400);
}

$res = Provider::importServices($providerId, $services, $marginType, $marginValue, $categoryId, $updateExists);
log_activity('admin', (int)$admin['id'], 'provider.import', 'provider', $providerId, json_encode($res));
json_response($res, $res['ok'] ? 200 : 400);
