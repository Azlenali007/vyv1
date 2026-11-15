<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$user = require_user_login();
$pdo  = Database::getConnection();
$ticketId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM support_tickets WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$ticketId, (int)$user['id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    set_flash('error', 'Ticket not found.');
    redirect('/tickets.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    if ($ticket['status'] === 'closed') {
        set_flash('error', 'This ticket is closed.');
    } else {
        $res = Ticket::reply($ticketId, 'user', (int)$user['id'], (string)($_POST['message'] ?? ''), $_FILES['attachment'] ?? null);
        if ($res['ok']) {
            set_flash('success', 'Reply sent.');
        } else {
            set_flash('error', $res['error']);
        }
    }
    redirect('/ticket-details.php?id=' . $ticketId);
}

$msgStmt = $pdo->prepare('SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY id ASC');
$msgStmt->execute([$ticketId]);
$messages = $msgStmt->fetchAll();

$pageTitle = 'Ticket #' . $ticket['ticket_code'];
include INCLUDES_PATH . '/user-header.php';
?>

<div class="mb-6">
    <a href="/tickets.php" class="text-xs font-semibold text-[#7138D8] hover:underline">&larr; Back to Tickets</a>
    <div class="flex flex-wrap items-center justify-between gap-3 mt-1">
        <h1 class="text-xl font-bold text-[#151515]"><?= e($ticket['subject']) ?></h1>
        <span class="px-3 py-1 rounded-lg border border-slate-200 bg-white text-xs font-semibold"><?= e(ucfirst($ticket['status'])) ?></span>
    </div>
    <p class="text-xs text-slate-500 mt-0.5 font-mono-code">#<?= e($ticket['ticket_code']) ?> · <?= e(ucfirst($ticket['department'])) ?></p>
</div>

<div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 space-y-4 mb-6">
    <?php foreach ($messages as $m):
        $isUser = $m['sender_type'] === 'user';
    ?>
        <div class="p-4 rounded-2xl border <?= $isUser ? 'bg-[#F8F6FF] border-[#7138D8]/20' : 'bg-slate-50 border-slate-200' ?>">
            <div class="flex items-center justify-between text-xs mb-2">
                <span class="font-bold <?= $isUser ? 'text-[#7138D8]' : 'text-[#151515]' ?>"><?= $isUser ? e($user['full_name']) : 'Support Staff' ?></span>
                <span class="text-slate-400"><?= e(date('d M Y, H:i', strtotime($m['created_at']))) ?></span>
            </div>
            <div class="text-sm text-slate-700 whitespace-pre-line"><?= e($m['message']) ?></div>
            <?php if (!empty($m['attachment'])): ?>
                <div class="mt-2">
                    <a href="/uploads/tickets/<?= e($m['attachment']) ?>" target="_blank" class="text-xs font-semibold text-[#7138D8] hover:underline">View Attachment</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($ticket['status'] !== 'closed'): ?>
<div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
    <h2 class="text-sm font-bold text-[#151515] mb-3">Send Reply</h2>
    <form method="post" enctype="multipart/form-data" class="space-y-4">
        <?= csrf_field() ?>
        <textarea name="message" rows="4" required placeholder="Write your reply..."
                  class="w-full p-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]"></textarea>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <input type="file" name="attachment" class="text-xs text-slate-600">
            <button type="submit" class="h-10 px-5 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-xs font-semibold">Send Reply</button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php include INCLUDES_PATH . '/user-footer.php'; ?>
