<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$user = require_user_login(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf(true);
    $input = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
    if (($input['action'] ?? '') === 'mark_all_read') {
        Notification::markAllReadForUser((int)$user['id']);
        json_response(['ok' => true]);
    }
    json_response(['ok' => false, 'error' => 'Invalid action.'], 400);
}

json_response([
    'ok'            => true,
    'unread_count'  => Notification::countUnreadForUser((int)$user['id']),
    'notifications' => Notification::getForUser((int)$user['id'], 10),
]);
