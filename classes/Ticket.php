<?php
declare(strict_types=1);

class Ticket
{
    public static function create(int $userId, string $subject, string $department, string $priority, string $message, ?int $orderId = null, ?array $file = null): array
    {
        $pdo = Database::getConnection();
        $subject = clean_text($subject, 200);
        $message = trim($message);

        if ($subject === '' || mb_strlen($subject) < 3) {
            return ['ok' => false, 'error' => 'Subject must be at least 3 characters.'];
        }
        if ($message === '' || mb_strlen($message) < 5) {
            return ['ok' => false, 'error' => 'Please describe your issue in detail.'];
        }

        $dept = in_array($department, ['order', 'payment', 'api', 'general'], true) ? $department : 'general';
        $prio = in_array($priority, ['low', 'medium', 'high'], true) ? $priority : 'medium';

        $attachment = null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $up = handle_secure_upload($file, TICKETS_UPLOAD_PATH, ALLOWED_TICKET_MIMES);
            if (!$up['ok']) {
                return ['ok' => false, 'error' => $up['error']];
            }
            $attachment = $up['filename'];
        }

        if ($orderId !== null && $orderId > 0) {
            $chk = $pdo->prepare('SELECT id FROM orders WHERE id = ? AND user_id = ? LIMIT 1');
            $chk->execute([$orderId, $userId]);
            if (!$chk->fetch()) {
                $orderId = null;
            }
        } else {
            $orderId = null;
        }

        $pdo->beginTransaction();
        try {
            $ticketCode = generate_code('TKT', 4);
            $stmt = $pdo->prepare(
                'INSERT INTO support_tickets (ticket_code, user_id, order_id, subject, department, priority, status, last_reply_at)
                 VALUES (?, ?, ?, ?, ?, ?, "open", NOW())'
            );
            $stmt->execute([$ticketCode, $userId, $orderId, $subject, $dept, $prio]);
            $ticketId = (int)$pdo->lastInsertId();

            $msgStmt = $pdo->prepare(
                'INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, attachment) VALUES (?, "user", ?, ?, ?)'
            );
            $msgStmt->execute([$ticketId, $userId, $message, $attachment]);

            $pdo->commit();
            log_activity('user', $userId, 'ticket.create', 'support_ticket', $ticketId, "Opened ticket #{$ticketCode}");
            return ['ok' => true, 'ticket_id' => $ticketId, 'ticket_code' => $ticketCode];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public static function reply(int $ticketId, string $senderType, int $senderId, string $message, ?array $file = null): array
    {
        $pdo = Database::getConnection();
        $message = trim($message);
        if ($message === '') {
            return ['ok' => false, 'error' => 'Reply message cannot be empty.'];
        }

        $tStmt = $pdo->prepare('SELECT * FROM support_tickets WHERE id = ? LIMIT 1');
        $tStmt->execute([$ticketId]);
        $ticket = $tStmt->fetch();
        if (!$ticket) {
            return ['ok' => false, 'error' => 'Support ticket not found.'];
        }

        $attachment = null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $up = handle_secure_upload($file, TICKETS_UPLOAD_PATH, ALLOWED_TICKET_MIMES);
            if (!$up['ok']) {
                return ['ok' => false, 'error' => $up['error']];
            }
            $attachment = $up['filename'];
        }

        $ins = $pdo->prepare(
            'INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, attachment) VALUES (?, ?, ?, ?, ?)'
        );
        $ins->execute([$ticketId, $senderType, $senderId, $message, $attachment]);

        $newStatus = $senderType === 'admin' ? TICKET_STATUS_ANSWERED : TICKET_STATUS_OPEN;
        $pdo->prepare('UPDATE support_tickets SET status = ?, last_reply_at = NOW() WHERE id = ?')
            ->execute([$newStatus, $ticketId]);

        if ($senderType === 'admin') {
            Notification::createForUser(
                (int)$ticket['user_id'],
                "Ticket Reply (#{$ticket['ticket_code']})",
                'Support staff replied to: ' . $ticket['subject'],
                'info',
                "/ticket-details.php?id={$ticketId}"
            );
        }

        return ['ok' => true];
    }
}
