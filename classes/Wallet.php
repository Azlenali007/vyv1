<?php
declare(strict_types=1);

class Wallet
{
    /**
     * Atomically credit a user's wallet. Must be called inside an active transaction or will start one.
     */
    public static function credit(
        int $userId,
        float $amount,
        string $type,
        string $description,
        ?string $refType = null,
        ?int $refId = null,
        ?int $adminId = null
    ): array {
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Credit amount must be positive.'];
        }

        $pdo = Database::getConnection();
        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare('SELECT id, balance FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new RuntimeException('User account not found.');
            }

            $before = round((float)$user['balance'], 4);
            $credit = round($amount, 4);
            $after  = round($before + $credit, 4);

            $upd = $pdo->prepare('UPDATE users SET balance = ? WHERE id = ?');
            $upd->execute([$after, $userId]);

            $txCode = generate_code('TX');
            $ins = $pdo->prepare(
                'INSERT INTO wallet_transactions
                 (tx_code, user_id, type, amount, balance_before, balance_after, reference_type, reference_id, description, created_by_admin_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $txCode,
                $userId,
                $type,
                $credit,
                $before,
                $after,
                $refType,
                $refId,
                mb_substr($description, 0, 255),
                $adminId,
            ]);

            if ($ownTx) {
                $pdo->commit();
            }

            return [
                'ok'             => true,
                'tx_code'        => $txCode,
                'balance_before' => $before,
                'balance_after'  => $after,
            ];
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Atomically debit a user's wallet with strict non-negative balance enforcement.
     */
    public static function debit(
        int $userId,
        float $amount,
        string $type,
        string $description,
        ?string $refType = null,
        ?int $refId = null,
        ?int $adminId = null,
        bool $trackSpent = false
    ): array {
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Debit amount must be positive.'];
        }

        $pdo = Database::getConnection();
        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare('SELECT id, balance, total_spent FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new RuntimeException('User account not found.');
            }

            $before = round((float)$user['balance'], 4);
            $debit  = round($amount, 4);

            if ($before < $debit) {
                throw new RuntimeException('Insufficient wallet balance. Available: ' . format_money($before) . ', Required: ' . format_money($debit));
            }

            $after = round($before - $debit, 4);
            if ($after < 0) {
                throw new RuntimeException('Wallet balance cannot become negative.');
            }

            if ($trackSpent) {
                $upd = $pdo->prepare('UPDATE users SET balance = ?, total_spent = total_spent + ? WHERE id = ?');
                $upd->execute([$after, $debit, $userId]);
            } else {
                $upd = $pdo->prepare('UPDATE users SET balance = ? WHERE id = ?');
                $upd->execute([$after, $userId]);
            }

            $txCode = generate_code('TX');
            $ins = $pdo->prepare(
                'INSERT INTO wallet_transactions
                 (tx_code, user_id, type, amount, balance_before, balance_after, reference_type, reference_id, description, created_by_admin_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $txCode,
                $userId,
                $type,
                $debit,
                $before,
                $after,
                $refType,
                $refId,
                mb_substr($description, 0, 255),
                $adminId,
            ]);

            if ($ownTx) {
                $pdo->commit();
            }

            return [
                'ok'             => true,
                'tx_code'        => $txCode,
                'balance_before' => $before,
                'balance_after'  => $after,
            ];
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public static function getTransactionsForUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY id DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll();
    }
}
