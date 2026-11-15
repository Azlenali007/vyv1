<?php
declare(strict_types=1);

class Order
{
    /**
     * Place an order atomically:
     * 1. Server-side service lookup & rate validation (never trust browser price/balance)
     * 2. Idempotency check to prevent duplicate submissions
     * 3. MySQL transaction + FOR UPDATE lock on user wallet + atomic debit
     * 4. If provider configured, forward via real cURL; handle failure & automatic refund safely
     */
    public static function place(
        int $userId,
        int $serviceId,
        string $link,
        int $quantity,
        ?string $customComments = null,
        ?string $idempotencyKey = null
    ): array {
        $pdo = Database::getConnection();
        $link = trim($link);

        if (!validate_url_or_target($link)) {
            return ['ok' => false, 'error' => 'Please enter a valid target URL or social handle.'];
        }

        // Prevent duplicate submission with same idempotency key
        if ($idempotencyKey) {
            $dup = $pdo->prepare('SELECT id, order_code FROM orders WHERE user_id = ? AND idempotency_key = ? LIMIT 1');
            $dup->execute([$userId, $idempotencyKey]);
            if ($existing = $dup->fetch()) {
                return ['ok' => false, 'error' => "Duplicate request prevented (Order #{$existing['order_code']})."];
            }
        }

        // Fetch authoritative service pricing from DB
        $svcStmt = $pdo->prepare(
            'SELECT s.*, c.status AS category_status
             FROM services s
             INNER JOIN categories c ON c.id = s.category_id
             WHERE s.id = ? LIMIT 1'
        );
        $svcStmt->execute([$serviceId]);
        $service = $svcStmt->fetch();

        if (!$service || $service['status'] !== 'active' || $service['category_status'] !== 'active') {
            return ['ok' => false, 'error' => 'Selected service is unavailable.'];
        }

        if ($quantity < (int)$service['min_qty'] || $quantity > (int)$service['max_qty']) {
            return [
                'ok'    => false,
                'error' => "Quantity must be between {$service['min_qty']} and {$service['max_qty']}.",
            ];
        }

        $ratePer1000   = (float)$service['rate_per_1000'];
        $providerRate  = (float)$service['provider_rate'];
        $charge        = round(($ratePer1000 / 1000) * $quantity, 4);
        $providerCost  = round(($providerRate / 1000) * $quantity, 4);
        $profit        = round($charge - $providerCost, 4);

        if ($charge <= 0) {
            return ['ok' => false, 'error' => 'Calculated order charge must be greater than zero.'];
        }

        $orderCode = generate_code('ORD', 4);
        $orderId   = 0;

        $pdo->beginTransaction();
        try {
            // Atomic debit inside transaction (locks user row FOR UPDATE)
            $debit = Wallet::debit(
                $userId,
                $charge,
                TX_TYPE_ORDER_CHARGE,
                "Order #{$orderCode} ({$service['name']} × {$quantity})",
                'order',
                null,
                null,
                true
            );

            if (!$debit['ok']) {
                throw new RuntimeException($debit['error']);
            }

            $ins = $pdo->prepare(
                'INSERT INTO orders
                 (order_code, user_id, service_id, provider_id, link, quantity, rate_per_1000, charge, provider_cost, profit, remains, custom_comments, status, idempotency_key)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", ?)'
            );
            $ins->execute([
                $orderCode,
                $userId,
                $serviceId,
                $service['provider_id'] ?: null,
                $link,
                $quantity,
                $ratePer1000,
                $charge,
                $providerCost,
                $profit,
                $quantity,
                $customComments ? clean_text($customComments, 4000) : null,
                $idempotencyKey,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            // Link wallet transaction reference_id to the new order
            $pdo->prepare('UPDATE wallet_transactions SET reference_id = ? WHERE tx_code = ?')
                ->execute([$orderId, $debit['tx_code']]);

            self::addLog($orderId, null, 'pending', 'Order placed and wallet charged ' . format_money($charge), 'user', $userId);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        // Dispatch to external provider via real cURL if configured
        if (!empty($service['provider_id']) && !empty($service['provider_service_id'])) {
            $provider = Provider::find((int)$service['provider_id']);
            if ($provider && $provider['status'] === 'active') {
                $apiParams = [
                    'service'  => $service['provider_service_id'],
                    'link'     => $link,
                    'quantity' => $quantity,
                ];
                if ($customComments) {
                    $apiParams['comments'] = $customComments;
                }

                $apiRes = Provider::request($provider, 'add', $apiParams);
                if ($apiRes['ok'] && !empty($apiRes['data']['order'])) {
                    $extOrderId = (string)$apiRes['data']['order'];
                    $pdo->prepare('UPDATE orders SET provider_order_id = ?, status = "processing", provider_response = ? WHERE id = ?')
                        ->execute([$extOrderId, json_encode($apiRes['data']), $orderId]);
                    self::addLog($orderId, 'pending', 'processing', "Dispatched to provider #{$provider['name']} (Ext ID: {$extOrderId})", 'provider', (int)$provider['id']);
                } else {
                    $err = $apiRes['error'] ?? 'Provider dispatch failed';
                    $pdo->prepare('UPDATE orders SET provider_response = ?, notes = ? WHERE id = ?')
                        ->execute([json_encode($apiRes), mb_substr($err, 0, 500), $orderId]);
                    self::addLog($orderId, 'pending', 'pending', "Provider dispatch warning: {$err}. Queued for admin review.", 'provider', (int)$provider['id']);
                }
            }
        }

        Notification::createForUser(
            $userId,
            "Order #{$orderCode} Placed",
            "{$service['name']} ({$quantity}) has been queued.",
            'info',
            "/order-details.php?id={$orderId}"
        );

        log_activity('user', $userId, 'order.place', 'order', $orderId, "Placed order #{$orderCode} for {$charge}");

        return [
            'ok'         => true,
            'order_id'   => $orderId,
            'order_code' => $orderCode,
            'charge'     => $charge,
        ];
    }

    public static function syncStatusFromProvider(int $orderId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            return ['ok' => false, 'error' => 'Order not found.'];
        }
        if (empty($order['provider_id']) || empty($order['provider_order_id'])) {
            return ['ok' => false, 'error' => 'Order is not linked to an external provider order ID yet.'];
        }

        $provider = Provider::find((int)$order['provider_id']);
        if (!$provider) {
            return ['ok' => false, 'error' => 'Provider record no longer exists.'];
        }

        $res = Provider::request($provider, 'status', ['order' => $order['provider_order_id']]);
        if (!$res['ok']) {
            return $res;
        }

        $data = $res['data'];
        $rawStatus = strtolower(trim((string)($data['status'] ?? '')));
        $mappedStatus = match ($rawStatus) {
            'completed'                => ORDER_STATUS_COMPLETED,
            'processing'               => ORDER_STATUS_PROCESSING,
            'in progress', 'in_progress' => ORDER_STATUS_IN_PROGRESS,
            'partial'                  => ORDER_STATUS_PARTIAL,
            'canceled', 'cancelled'    => ORDER_STATUS_CANCELLED,
            'pending'                  => ORDER_STATUS_PENDING,
            default                    => $order['status'],
        };

        $startCount = isset($data['start_count']) ? max(0, (int)$data['start_count']) : (int)$order['start_count'];
        $remains    = isset($data['remains']) ? max(0, (int)$data['remains']) : (int)$order['remains'];

        $pdo->prepare('UPDATE orders SET status = ?, start_count = ?, remains = ?, provider_response = ? WHERE id = ?')
            ->execute([$mappedStatus, $startCount, $remains, json_encode($data), $orderId]);

        if ($mappedStatus !== $order['status']) {
            self::addLog($orderId, $order['status'], $mappedStatus, "Status synchronized from provider ({$rawStatus})", 'provider', (int)$provider['id']);
        }

        // Automatically process eligible refund if cancelled or partial
        if (in_array($mappedStatus, [ORDER_STATUS_CANCELLED, ORDER_STATUS_PARTIAL], true)) {
            self::processRefund($orderId, null, 'system');
        }

        return ['ok' => true, 'status' => $mappedStatus, 'start_count' => $startCount, 'remains' => $remains];
    }

    /**
     * Process eligible refund atomically with duplicate refund prevention (FOR UPDATE + is_refunded guard).
     */
    public static function processRefund(int $orderId, ?int $adminId = null, string $actorType = 'admin'): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new RuntimeException('Order not found.');
            }
            if ((int)$order['is_refunded'] === 1) {
                throw new RuntimeException('This order has already been refunded. Duplicate refunds are blocked.');
            }

            $status = $order['status'];
            if (!in_array($status, [ORDER_STATUS_CANCELLED, ORDER_STATUS_FAILED, ORDER_STATUS_PARTIAL], true)) {
                throw new RuntimeException("Orders in '{$status}' status are not eligible for automatic refund unless cancelled/failed/partial.");
            }

            $refundAmount = 0.0;
            if ($status === ORDER_STATUS_PARTIAL && (int)$order['quantity'] > 0 && (int)$order['remains'] > 0) {
                $ratio = min(1.0, (int)$order['remains'] / (int)$order['quantity']);
                $refundAmount = round((float)$order['charge'] * $ratio, 4);
            } elseif (in_array($status, [ORDER_STATUS_CANCELLED, ORDER_STATUS_FAILED], true)) {
                $refundAmount = round((float)$order['charge'], 4);
            }

            if ($refundAmount <= 0) {
                throw new RuntimeException('Calculated refund amount is zero.');
            }

            $credit = Wallet::credit(
                (int)$order['user_id'],
                $refundAmount,
                TX_TYPE_REFUND,
                "Refund for Order #{$order['order_code']} ({$status})",
                'order',
                $orderId,
                $adminId
            );

            if (!$credit['ok']) {
                throw new RuntimeException($credit['error']);
            }

            $newStatus = ($status === ORDER_STATUS_PARTIAL) ? ORDER_STATUS_PARTIAL : ORDER_STATUS_REFUNDED;
            $pdo->prepare('UPDATE orders SET is_refunded = 1, refunded_amount = ?, status = ? WHERE id = ?')
                ->execute([$refundAmount, $newStatus, $orderId]);

            // Adjust total_spent cleanly
            $pdo->prepare('UPDATE users SET total_spent = GREATEST(0, total_spent - ?) WHERE id = ?')
                ->execute([$refundAmount, $order['user_id']]);

            self::addLog($orderId, $status, $newStatus, 'Refunded ' . format_money($refundAmount) . ' to user wallet', $actorType, $adminId);

            $pdo->commit();

            Notification::createForUser(
                (int)$order['user_id'],
                "Refund Processed (#{$order['order_code']})",
                format_money($refundAmount) . ' has been credited back to your wallet.',
                'success',
                "/order-details.php?id={$orderId}"
            );

            return ['ok' => true, 'refunded_amount' => $refundAmount, 'tx_code' => $credit['tx_code']];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public static function addLog(int $orderId, ?string $oldStatus, string $newStatus, string $message, string $actorType = 'system', ?int $actorId = null): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO order_logs (order_id, old_status, new_status, message, actor_type, actor_id) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orderId, $oldStatus, $newStatus, mb_substr($message, 0, 500), $actorType, $actorId]);
    }
}
