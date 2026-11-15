<?php
declare(strict_types=1);
/**
 * Cron script: Synchronize active provider orders via cURL.
 * Usage: php cron/update-orders.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once dirname(__DIR__) . '/config/app.php';

$pdo = Database::getConnection();
$stmt = $pdo->query(
    "SELECT id, order_code FROM orders
     WHERE provider_id IS NOT NULL
       AND provider_order_id IS NOT NULL
       AND status IN ('pending', 'processing', 'in_progress')
     ORDER BY updated_at ASC LIMIT 50"
);

$orders = $stmt->fetchAll();
$updated = 0;
foreach ($orders as $ord) {
    $res = Order::syncStatusFromProvider((int)$ord['id']);
    if ($res['ok']) {
        $updated++;
        echo "[OK] #{$ord['order_code']} -> {$res['status']}\n";
    } else {
        echo "[WARN] #{$ord['order_code']} -> {$res['error']}\n";
    }
}
echo "Synced {$updated}/" . count($orders) . " orders.\n";
