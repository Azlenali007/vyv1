<?php
declare(strict_types=1);
/**
 * Cron script: Process eligible refunds for cancelled, failed, or partial orders not yet refunded.
 * Usage: php cron/process-refunds.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once dirname(__DIR__) . '/config/app.php';

$pdo = Database::getConnection();
$stmt = $pdo->query(
    "SELECT id, order_code FROM orders
     WHERE is_refunded = 0
       AND status IN ('cancelled', 'failed', 'partial')
     ORDER BY id ASC LIMIT 100"
);

foreach ($stmt->fetchAll() as $ord) {
    $res = Order::processRefund((int)$ord['id'], null, 'cron');
    if ($res['ok']) {
        echo "[REFUNDED] #{$ord['order_code']} -> {$res['refunded_amount']}\n";
    } else {
        echo "[SKIP] #{$ord['order_code']} -> {$res['error']}\n";
    }
}
