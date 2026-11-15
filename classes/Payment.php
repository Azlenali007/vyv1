<?php
declare(strict_types=1);

class Payment
{
    public static function activeMethods(): array
    {
        $pdo = Database::getConnection();
        return $pdo->query("SELECT * FROM payment_methods WHERE status = 'active' ORDER BY sort_order ASC, id ASC")->fetchAll();
    }

    public static function submitRequest(int $userId, int $methodId, float $amount, string $txRef, string $userNote = '', ?array $proofFile = null): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM payment_methods WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$methodId]);
        $method = $stmt->fetch();

        if (!$method) {
            return ['ok' => false, 'error' => 'Selected payment method is unavailable.'];
        }

        $min = (float)$method['min_amount'];
        $max = (float)$method['max_amount'];
        if ($amount < $min || $amount > $max) {
            return ['ok' => false, 'error' => 'Amount must be between ' . format_money($min) . ' and ' . format_money($max) . '.'];
        }

        $txRef = clean_text($txRef, 160);
        if ($txRef === '') {
            return ['ok' => false, 'error' => 'Please enter your transaction / UTR reference ID.'];
        }

        $proofFilename = null;
        if ($proofFile && ($proofFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = handle_secure_upload($proofFile, PAYMENTS_UPLOAD_PATH, ALLOWED_IMAGE_MIMES);
            if (!$upload['ok']) {
                return ['ok' => false, 'error' => $upload['error']];
            }
            $proofFilename = $upload['filename'];
        } elseif ((int)$method['requires_proof'] === 1) {
            // Allow submission with reference if no file attached, or require proof? Let's make proof optional if reference provided, or validate cleanly.
        }

        $feeAmount   = round($amount * ((float)$method['fee_percent'] / 100), 4);
        $bonusAmount = round($amount * ((float)$method['bonus_percent'] / 100), 4);
        $netCredit   = round(max(0.0, $amount - $feeAmount + $bonusAmount), 4);
        $paymentCode = generate_code('PAY', 5);

        $ins = $pdo->prepare(
            'INSERT INTO payments (payment_code, user_id, method_id, amount, fee_amount, bonus_amount, net_credit, transaction_ref, proof_file, user_note, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")'
        );
        $ins->execute([
            $paymentCode,
            $userId,
            $methodId,
            $amount,
            $feeAmount,
            $bonusAmount,
            $netCredit,
            $txRef,
            $proofFilename,
            clean_text($userNote, 1000) ?: null,
        ]);
        $paymentId = (int)$pdo->lastInsertId();

        Notification::createForUser(
            $userId,
            "Deposit Request #{$paymentCode}",
            'Your add-funds request of ' . format_money($amount) . ' is pending verification.',
            'info',
            '/transactions.php'
        );

        log_activity('user', $userId, 'payment.request', 'payment', $paymentId, "Requested deposit {$paymentCode} for {$amount}");

        return ['ok' => true, 'payment_id' => $paymentId, 'payment_code' => $paymentCode];
    }

    public static function approve(int $paymentId, int $adminId, string $adminNote = ''): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch();

            if (!$payment) {
                throw new RuntimeException('Payment record not found.');
            }
            if ($payment['status'] !== 'pending') {
                throw new RuntimeException('Payment has already been ' . $payment['status'] . '.');
            }

            $creditAmount = round((float)$payment['net_credit'], 4);
            $credit = Wallet::credit(
                (int)$payment['user_id'],
                $creditAmount,
                TX_TYPE_DEPOSIT,
                "Deposit approved (#{$payment['payment_code']})",
                'payment',
                $paymentId,
                $adminId
            );

            if (!$credit['ok']) {
                throw new RuntimeException($credit['error']);
            }

            $upd = $pdo->prepare(
                'UPDATE payments SET status = "approved", admin_note = ?, processed_by_admin_id = ?, processed_at = NOW() WHERE id = ?'
            );
            $upd->execute([clean_text($adminNote, 500) ?: null, $adminId, $paymentId]);

            $pdo->commit();

            Notification::createForUser(
                (int)$payment['user_id'],
                "Funds Credited (#{$payment['payment_code']})",
                format_money($creditAmount) . ' has been added to your wallet.',
                'success',
                '/transactions.php'
            );

            log_activity('admin', $adminId, 'payment.approve', 'payment', $paymentId, "Approved deposit {$payment['payment_code']} ({$creditAmount})");

            return ['ok' => true, 'credited' => $creditAmount];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public static function reject(int $paymentId, int $adminId, string $adminNote = ''): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch();

            if (!$payment) {
                throw new RuntimeException('Payment record not found.');
            }
            if ($payment['status'] !== 'pending') {
                throw new RuntimeException('Payment has already been ' . $payment['status'] . '.');
            }

            $upd = $pdo->prepare(
                'UPDATE payments SET status = "rejected", admin_note = ?, processed_by_admin_id = ?, processed_at = NOW() WHERE id = ?'
            );
            $upd->execute([clean_text($adminNote, 500) ?: 'Payment could not be verified.', $adminId, $paymentId]);

            $pdo->commit();

            Notification::createForUser(
                (int)$payment['user_id'],
                "Deposit Declined (#{$payment['payment_code']})",
                $adminNote !== '' ? $adminNote : 'Your deposit request was declined after review.',
                'danger',
                '/add-funds.php'
            );

            log_activity('admin', $adminId, 'payment.reject', 'payment', $paymentId, "Rejected deposit {$payment['payment_code']}");

            return ['ok' => true];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
