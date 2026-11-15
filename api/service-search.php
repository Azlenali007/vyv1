<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$q   = trim((string)($_GET['q'] ?? ''));
$cat = (int)($_GET['category_id'] ?? 0);

$services = Service::search($q, $cat, true);
json_response([
    'ok'       => true,
    'currency' => get_setting('currency_symbol', DEFAULT_CURRENCY_SYMBOL),
    'services' => $services,
]);
