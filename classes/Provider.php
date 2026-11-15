<?php
declare(strict_types=1);

class Provider
{
    public static function all(): array
    {
        $pdo = Database::getConnection();
        return $pdo->query(
            'SELECT p.*, (SELECT COUNT(*) FROM services s WHERE s.provider_id = p.id) AS linked_services
             FROM providers p ORDER BY p.id DESC'
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM providers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): array
    {
        $name   = clean_text($data['name'] ?? '', 120);
        $apiUrl = trim((string)($data['api_url'] ?? ''));
        $apiKey = trim((string)($data['api_key'] ?? ''));

        if ($name === '' || !filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'error' => 'Valid provider name and API URL are required.'];
        }
        if ($apiKey === '') {
            return ['ok' => false, 'error' => 'Provider API key is required.'];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO providers (name, api_url, api_key, api_type, currency, exchange_rate, connection_status, status)
             VALUES (?, ?, ?, ?, ?, ?, "unverified", ?)'
        );
        $stmt->execute([
            $name,
            $apiUrl,
            $apiKey,
            ($data['api_type'] ?? 'standard_v2') === 'custom' ? 'custom' : 'standard_v2',
            strtoupper(clean_text($data['currency'] ?? 'INR', 10) ?: 'INR'),
            max(0.000001, (float)($data['exchange_rate'] ?? 1.0)),
            ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ]);

        return ['ok' => true, 'id' => (int)$pdo->lastInsertId()];
    }

    public static function update(int $id, array $data): array
    {
        $provider = self::find($id);
        if (!$provider) {
            return ['ok' => false, 'error' => 'Provider not found.'];
        }

        $name   = clean_text($data['name'] ?? '', 120);
        $apiUrl = trim((string)($data['api_url'] ?? ''));
        $apiKey = trim((string)($data['api_key'] ?? ''));
        if ($apiKey === '') {
            $apiKey = $provider['api_key'];
        }

        if ($name === '' || !filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'error' => 'Valid provider name and API URL are required.'];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE providers SET name = ?, api_url = ?, api_key = ?, api_type = ?, currency = ?, exchange_rate = ?, status = ? WHERE id = ?'
        );
        $stmt->execute([
            $name,
            $apiUrl,
            $apiKey,
            ($data['api_type'] ?? 'standard_v2') === 'custom' ? 'custom' : 'standard_v2',
            strtoupper(clean_text($data['currency'] ?? 'INR', 10) ?: 'INR'),
            max(0.000001, (float)($data['exchange_rate'] ?? 1.0)),
            ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            $id,
        ]);

        return ['ok' => true];
    }

    /**
     * Real PHP cURL POST call to an SMM provider API (standard v2 key+action POST protocol).
     * Never returns fake success; logs actual HTTP status, latency, and response payload.
     */
    public static function request(array $provider, string $action, array $params = []): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'PHP cURL extension is not available on this server.'];
        }

        $url = trim((string)($provider['api_url'] ?? ''));
        $key = trim((string)($provider['api_key'] ?? ''));

        if ($url === '' || $key === '') {
            return ['ok' => false, 'error' => 'Provider API URL or API Key is not configured.'];
        }

        $payload = array_merge(['key' => $key, 'action' => $action], $params);
        $maskedPayload = $payload;
        $maskedPayload['key'] = '***REDACTED***';

        $startTime = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'ApexSMMPanel-PHP-Client/1.0',
        ]);

        $rawResponse = curl_exec($ch);
        $curlErr     = curl_error($ch);
        $httpCode    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $durationMs  = (int)round((microtime(true) - $startTime) * 1000);
        curl_close($ch);

        $decoded = is_string($rawResponse) ? json_decode($rawResponse, true) : null;
        $isSuccess = ($rawResponse !== false && $httpCode >= 200 && $httpCode < 300 && is_array($decoded) && !isset($decoded['error']));

        $errMsg = null;
        if ($curlErr !== '') {
            $errMsg = 'cURL error: ' . $curlErr;
        } elseif ($httpCode < 200 || $httpCode >= 300) {
            $errMsg = "Provider returned HTTP {$httpCode}.";
        } elseif (!is_array($decoded)) {
            $errMsg = 'Provider returned non-JSON response.';
        } elseif (isset($decoded['error'])) {
            $errMsg = (string)$decoded['error'];
        }

        self::logApiCall(
            (int)$provider['id'],
            null,
            'outbound_provider',
            $url,
            $action,
            json_encode($maskedPayload),
            is_string($rawResponse) ? mb_substr($rawResponse, 0, 65000) : null,
            $httpCode,
            $durationMs,
            $isSuccess,
            $errMsg
        );

        if (!$isSuccess) {
            return ['ok' => false, 'error' => $errMsg ?: 'Unknown provider communication error.', 'http_code' => $httpCode];
        }

        return ['ok' => true, 'data' => $decoded, 'http_code' => $httpCode, 'duration_ms' => $durationMs];
    }

    public static function testConnection(int $providerId): array
    {
        $provider = self::find($providerId);
        if (!$provider) {
            return ['ok' => false, 'error' => 'Provider not found.'];
        }

        $res = self::request($provider, 'balance');
        $pdo = Database::getConnection();

        if (!$res['ok']) {
            $stmt = $pdo->prepare('UPDATE providers SET connection_status = "error", last_error = ?, balance_checked_at = NOW() WHERE id = ?');
            $stmt->execute([mb_substr($res['error'], 0, 500), $providerId]);
            return $res;
        }

        $balance = isset($res['data']['balance']) ? (float)$res['data']['balance'] : null;
        if ($balance === null) {
            $msg = 'Connected, but provider response did not contain a "balance" field.';
            $stmt = $pdo->prepare('UPDATE providers SET connection_status = "error", last_error = ?, balance_checked_at = NOW() WHERE id = ?');
            $stmt->execute([$msg, $providerId]);
            return ['ok' => false, 'error' => $msg];
        }

        $currency = strtoupper((string)($res['data']['currency'] ?? $provider['currency']));
        $stmt = $pdo->prepare('UPDATE providers SET balance = ?, currency = ?, connection_status = "connected", last_error = NULL, balance_checked_at = NOW() WHERE id = ?');
        $stmt->execute([$balance, mb_substr($currency, 0, 10), $providerId]);

        return ['ok' => true, 'balance' => $balance, 'currency' => $currency, 'duration_ms' => $res['duration_ms']];
    }

    public static function fetchServices(int $providerId): array
    {
        $provider = self::find($providerId);
        if (!$provider) {
            return ['ok' => false, 'error' => 'Provider not found.'];
        }

        $res = self::request($provider, 'services');
        if (!$res['ok']) {
            return $res;
        }

        if (!is_array($res['data']) || !array_is_list($res['data'])) {
            return ['ok' => false, 'error' => 'Provider did not return a valid list of services.'];
        }

        return ['ok' => true, 'services' => $res['data'], 'provider' => $provider];
    }

    public static function importServices(
        int $providerId,
        array $externalServices,
        string $marginType = 'percentage',
        float $marginValue = 30.0,
        ?int $targetCategoryId = null,
        bool $updateExisting = true
    ): array {
        $provider = self::find($providerId);
        if (!$provider) {
            return ['ok' => false, 'error' => 'Provider not found.'];
        }

        $pdo = Database::getConnection();
        $exchangeRate = max(0.000001, (float)$provider['exchange_rate']);
        $imported = 0;
        $updated  = 0;
        $skipped  = 0;

        foreach ($externalServices as $ext) {
            $extId   = trim((string)($ext['service'] ?? $ext['id'] ?? ''));
            $extName = clean_text((string)($ext['name'] ?? ''), 255);
            if ($extId === '' || $extName === '') {
                $skipped++;
                continue;
            }

            $rawRate      = max(0.0, (float)($ext['rate'] ?? 0));
            $providerRate = round($rawRate * $exchangeRate, 4);
            $minQty       = max(1, (int)($ext['min'] ?? 10));
            $maxQty       = max($minQty, (int)($ext['max'] ?? 100000));
            $extCategory  = clean_text((string)($ext['category'] ?? 'Imported Services'), 120);
            $extDesc      = clean_text((string)($ext['desc'] ?? $ext['description'] ?? ''), 3000);
            $refill       = !empty($ext['refill']) ? 1 : 0;
            $cancel       = !empty($ext['cancel']) ? 1 : 0;
            $dripfeed     = !empty($ext['dripfeed']) ? 1 : 0;

            $catId = $targetCategoryId ?: Category::findOrCreateByName($extCategory, $extCategory);
            $sellingPrice = Service::calculateSellingPrice($providerRate, $marginType, $marginValue);

            // Duplicate check via provider_service_mappings
            $mapStmt = $pdo->prepare(
                'SELECT id, service_id FROM provider_service_mappings WHERE provider_id = ? AND external_service_id = ? LIMIT 1'
            );
            $mapStmt->execute([$providerId, $extId]);
            $existing = $mapStmt->fetch();

            if ($existing) {
                if (!$updateExisting) {
                    $skipped++;
                    continue;
                }
                $svcUpd = $pdo->prepare(
                    'UPDATE services SET provider_rate = ?, margin_type = ?, margin_value = ?, rate_per_1000 = ?, min_qty = ?, max_qty = ?, last_synced_at = NOW() WHERE id = ?'
                );
                $svcUpd->execute([$providerRate, $marginType, $marginValue, $sellingPrice, $minQty, $maxQty, $existing['service_id']]);

                $mapUpd = $pdo->prepare(
                    'UPDATE provider_service_mappings SET external_service_name = ?, external_category = ?, external_rate = ?, external_min = ?, external_max = ?, last_synced_at = NOW() WHERE id = ?'
                );
                $mapUpd->execute([$extName, $extCategory, $rawRate, $minQty, $maxQty, $existing['id']]);
                $updated++;
            } else {
                $svcIns = $pdo->prepare(
                    'INSERT INTO services
                     (category_id, provider_id, provider_service_id, name, description, service_type, provider_rate, margin_type, margin_value, rate_per_1000, min_qty, max_qty, refill_supported, cancel_supported, dripfeed_supported, status, last_synced_at)
                     VALUES (?, ?, ?, ?, ?, "default", ?, ?, ?, ?, ?, ?, ?, ?, ?, "active", NOW())'
                );
                $svcIns->execute([
                    $catId,
                    $providerId,
                    $extId,
                    $extName,
                    $extDesc,
                    $providerRate,
                    $marginType,
                    $marginValue,
                    $sellingPrice,
                    $minQty,
                    $maxQty,
                    $refill,
                    $cancel,
                    $dripfeed,
                ]);
                $serviceId = (int)$pdo->lastInsertId();

                $mapIns = $pdo->prepare(
                    'INSERT INTO provider_service_mappings
                     (provider_id, service_id, external_service_id, external_service_name, external_category, external_rate, external_min, external_max, last_synced_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $mapIns->execute([$providerId, $serviceId, $extId, $extName, $extCategory, $rawRate, $minQty, $maxQty]);
                $imported++;
            }
        }

        return [
            'ok'       => true,
            'imported' => $imported,
            'updated'  => $updated,
            'skipped'  => $skipped,
        ];
    }

    public static function syncPrices(int $providerId): array
    {
        $fetched = self::fetchServices($providerId);
        if (!$fetched['ok']) {
            return $fetched;
        }

        $provider = $fetched['provider'];
        $exchangeRate = max(0.000001, (float)$provider['exchange_rate']);
        $byExtId = [];
        foreach ($fetched['services'] as $ext) {
            $id = trim((string)($ext['service'] ?? $ext['id'] ?? ''));
            if ($id !== '') {
                $byExtId[$id] = $ext;
            }
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT m.id AS map_id, m.external_service_id, m.auto_sync_price, s.id AS service_id, s.margin_type, s.margin_value
             FROM provider_service_mappings m
             INNER JOIN services s ON s.id = m.service_id
             WHERE m.provider_id = ?'
        );
        $stmt->execute([$providerId]);
        $updated = 0;

        foreach ($stmt->fetchAll() as $row) {
            if (!(bool)$row['auto_sync_price']) {
                continue;
            }
            $ext = $byExtId[$row['external_service_id']] ?? null;
            if (!$ext) {
                continue;
            }
            $rawRate      = max(0.0, (float)($ext['rate'] ?? 0));
            $providerRate = round($rawRate * $exchangeRate, 4);
            $sellingRate  = Service::calculateSellingPrice($providerRate, $row['margin_type'], (float)$row['margin_value']);
            $minQty       = max(1, (int)($ext['min'] ?? 10));
            $maxQty       = max($minQty, (int)($ext['max'] ?? 100000));

            $pdo->prepare('UPDATE services SET provider_rate = ?, rate_per_1000 = ?, min_qty = ?, max_qty = ?, last_synced_at = NOW() WHERE id = ?')
                ->execute([$providerRate, $sellingRate, $minQty, $maxQty, $row['service_id']]);
            $pdo->prepare('UPDATE provider_service_mappings SET external_rate = ?, external_min = ?, external_max = ?, last_synced_at = NOW() WHERE id = ?')
                ->execute([$rawRate, $minQty, $maxQty, $row['map_id']]);
            $updated++;
        }

        return ['ok' => true, 'updated' => $updated];
    }

    public static function logApiCall(
        ?int $providerId,
        ?int $userId,
        string $direction,
        string $endpoint,
        string $action,
        ?string $requestPayload,
        ?string $responsePayload,
        ?int $httpCode,
        ?int $durationMs,
        bool $isSuccess,
        ?string $errorMessage
    ): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'INSERT INTO api_logs
                 (provider_id, user_id, direction, endpoint, action, request_payload, response_payload, http_code, duration_ms, is_success, error_message)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $providerId,
                $userId,
                $direction,
                mb_substr($endpoint, 0, 255),
                mb_substr($action, 0, 80),
                $requestPayload,
                $responsePayload,
                $httpCode,
                $durationMs,
                $isSuccess ? 1 : 0,
                $errorMessage ? mb_substr($errorMessage, 0, 500) : null,
            ]);
        } catch (Throwable $e) {
            error_log('API log error: ' . $e->getMessage());
        }
    }
}
