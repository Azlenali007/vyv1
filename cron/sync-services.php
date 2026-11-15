<?php
declare(strict_types=1);
/**
 * Cron script: Synchronize service prices from active providers.
 * Usage: php cron/sync-services.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once dirname(__DIR__) . '/config/app.php';

$providers = Provider::all();
foreach ($providers as $p) {
    if ($p['status'] !== 'active') {
        continue;
    }
    $res = Provider::syncPrices((int)$p['id']);
    if ($res['ok']) {
        echo "[OK] Provider {$p['name']}: updated {$res['updated']} services.\n";
    } else {
        echo "[ERR] Provider {$p['name']}: {$res['error']}\n";
    }
}
