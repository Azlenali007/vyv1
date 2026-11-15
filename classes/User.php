<?php
declare(strict_types=1);

class User
{
    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function updateProfile(int $userId, string $fullName, string $email, string $timezone): array
    {
        $pdo = Database::getConnection();
        $fullName = clean_text($fullName, 120);
        $email    = strtolower(trim($email));
        $timezone = clean_text($timezone, 64) ?: 'Asia/Kolkata';

        if ($fullName === '' || mb_strlen($fullName) < 2) {
            return ['ok' => false, 'error' => 'Full name must be at least 2 characters.'];
        }
        if (!validate_email($email)) {
            return ['ok' => false, 'error' => 'Invalid email address.'];
        }

        $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $dup->execute([$email, $userId]);
        if ($dup->fetch()) {
            return ['ok' => false, 'error' => 'That email address is already associated with another account.'];
        }

        $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, timezone = ? WHERE id = ?');
        $stmt->execute([$fullName, $email, $timezone, $userId]);
        log_activity('user', $userId, 'user.profile_update', 'user', $userId, 'Updated profile information');

        return ['ok' => true];
    }

    public static function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $pdo = Database::getConnection();
        $user = self::find($userId);
        if (!$user) {
            return ['ok' => false, 'error' => 'User account not found.'];
        }
        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Current password is incorrect.'];
        }
        if ($err = validate_password($newPassword)) {
            return ['ok' => false, 'error' => $err];
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $userId]);
        log_activity('user', $userId, 'user.password_change', 'user', $userId, 'Changed account password');

        return ['ok' => true];
    }

    public static function regenerateApiKey(int $userId): string
    {
        $pdo = Database::getConnection();
        $newKey = bin2hex(random_bytes(24));
        $stmt = $pdo->prepare('UPDATE users SET api_key = ? WHERE id = ?');
        $stmt->execute([$newKey, $userId]);
        log_activity('user', $userId, 'user.api_key_regenerate', 'user', $userId, 'Regenerated API key');
        return $newKey;
    }

    public static function createByAdmin(array $data, int $adminId): array
    {
        $pdo = Database::getConnection();
        $username = trim($data['username'] ?? '');
        $fullName = clean_text($data['full_name'] ?? '', 120);
        $email    = strtolower(trim($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $balance  = max(0.0, (float)($data['balance'] ?? 0));
        $status   = in_array($data['status'] ?? 'active', ['active', 'suspended', 'banned'], true) ? $data['status'] : 'active';

        if (!validate_username($username)) {
            return ['ok' => false, 'error' => 'Invalid username (3–40 alphanumeric/underscore).'];
        }
        if ($fullName === '') {
            return ['ok' => false, 'error' => 'Full name is required.'];
        }
        if (!validate_email($email)) {
            return ['ok' => false, 'error' => 'Invalid email address.'];
        }
        if ($err = validate_password($password)) {
            return ['ok' => false, 'error' => $err];
        }

        $dup = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $dup->execute([$username, $email]);
        if ($dup->fetch()) {
            return ['ok' => false, 'error' => 'Username or email already exists.'];
        }

        Database::beginTransaction();
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $apiKey = bin2hex(random_bytes(24));
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, full_name, email, password_hash, balance, api_key, status)
                 VALUES (?, ?, ?, ?, 0.0000, ?, ?)'
            );
            $stmt->execute([$username, $fullName, $email, $hash, $apiKey, $status]);
            $userId = (int)$pdo->lastInsertId();

            if ($balance > 0) {
                Wallet::credit($userId, $balance, TX_TYPE_ADMIN_CREDIT, 'Initial balance granted on account creation', 'admin', $adminId, $adminId);
            }

            Database::commit();
            log_activity('admin', $adminId, 'admin.user_create', 'user', $userId, "Created user {$username}");
            return ['ok' => true, 'user_id' => $userId];
        } catch (Throwable $e) {
            Database::rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
