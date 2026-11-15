<?php
declare(strict_types=1);

class Notification
{
    public static function createForUser(int $userId, string $title, string $message, string $type = 'info', ?string $link = null): void
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, mb_substr($title, 0, 160), mb_substr($message, 0, 500), $type, $link]);
        } catch (Throwable $e) {
            error_log('Notification error: ' . $e->getMessage());
        }
    }

    public static function createForAdmin(?int $adminId, string $title, string $message, string $type = 'info', ?string $link = null): void
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'INSERT INTO notifications (admin_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$adminId, mb_substr($title, 0, 160), mb_substr($message, 0, 500), $type, $link]);
        } catch (Throwable $e) {
            error_log('Admin notification error: ' . $e->getMessage());
        }
    }

    public static function getForUser(int $userId, int $limit = 10): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ?');
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    public static function countUnreadForUser(int $userId): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function markAllReadForUser(int $userId): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
    }
}
