<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$user = require_user_login(true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
require_valid_csrf(true);

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input['action'] ?? 'create';
if ($action === 'reply') {
    $ticketId = (int)($input['ticket_id'] ?? 0);
    $pdo = Database::getConnection();
    $chk = $pdo->prepare('SELECT id FROM support_tickets WHERE id = ? AND user_id = ? LIMIT 1');
    $chk->execute([$ticketId, (int)$user['id']]);
    if (!$chk->fetch()) {
        json_response(['ok' => false, 'error' => 'Ticket not found.'], 404);
    }
    $res = Ticket::reply($ticketId, 'user', (int)$user['id'], (string)($input['message'] ?? ''));
    json_response($res, $res['ok'] ? 200 : 400);
}

$res = Ticket::create(
    (int)$user['id'],
    (string)($input['subject'] ?? ''),
    (string)($input['department'] ?? 'general'),
    (string)($input['priority'] ?? 'medium'),
    (string)($input['message'] ?? ''),
    !empty($input['order_id']) ? (int)$input['order_id'] : null
);
json_response($res, $res['ok'] ? 200 : 400);
