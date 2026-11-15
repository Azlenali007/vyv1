<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$user = require_user_login(true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
require_valid_csrf(true);

$res = Payment::submitRequest(
    (int)$user['id'],
    (int)($_POST['method_id'] ?? 0),
    (float)($_POST['amount'] ?? 0),
    (string)($_POST['transaction_ref'] ?? ''),
    (string)($_POST['user_note'] ?? ''),
    $_FILES['proof_file'] ?? null
);

json_response($res, $res['ok'] ? 200 : 400);
