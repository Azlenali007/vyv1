<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_login(true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
require_valid_csrf(true);

$input = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
$entity = $input['entity'] ?? '';
$action = $input['action'] ?? '';
$ids    = $input['ids'] ?? [];

if (!is_array($ids) || empty($ids)) {
    json_response(['ok' => false, 'error' => 'No records selected.'], 400);
}

if ($entity === 'services') {
    require_admin_permission('services.manage', true);
    $res = Service::bulkAction($action, $ids, $input);
    log_activity('admin', (int)$admin['id'], "bulk.services.{$action}", 'service', null, json_encode(['ids' => $ids, 'result' => $res]));
    json_response($res, $res['ok'] ? 200 : 400);
}

if ($entity === 'categories') {
    require_admin_permission('categories.manage', true);
    if ($action === 'delete') {
        $res = Category::bulkDelete($ids);
        log_activity('admin', (int)$admin['id'], 'bulk.categories.delete', 'category', null, json_encode($ids));
        json_response($res, $res['ok'] ? 200 : 400);
    }
    if (in_array($action, ['enable', 'disable'], true)) {
        $cleanIds = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        $ph = implode(',', array_fill(0, count($cleanIds), '?'));
        $newStatus = $action === 'enable' ? 'active' : 'inactive';
        $stmt = Database::getConnection()->prepare("UPDATE categories SET status = ? WHERE id IN ($ph)");
        $stmt->execute(array_merge([$newStatus], $cleanIds));
        json_response(['ok' => true, 'affected' => $stmt->rowCount()]);
    }
}

json_response(['ok' => false, 'error' => 'Unsupported bulk operation.'], 400);
