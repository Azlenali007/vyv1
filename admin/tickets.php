<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('tickets.manage');
$pdo   = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $action   = $_POST['action'] ?? '';
    if ($action === 'reply') {
        $res = Ticket::reply($ticketId, 'admin', (int)$admin['id'], (string)($_POST['message'] ?? ''));
        set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Reply posted.' : $res['error']);
    } elseif ($action === 'close') {
        $pdo->prepare("UPDATE support_tickets SET status = 'closed' WHERE id = ?")->execute([$ticketId]);
        set_flash('success', 'Ticket closed.');
    }
    redirect('/admin/tickets.php?view=' . $ticketId);
}

$tickets = $pdo->query(
    'SELECT t.*, u.username FROM support_tickets t INNER JOIN users u ON u.id = t.user_id ORDER BY FIELD(t.status, "open", "answered", "closed"), t.id DESC'
)->fetchAll();

$viewId = (int)($_GET['view'] ?? ($tickets[0]['id'] ?? 0));
$activeTicket = null;
$messages = [];
if ($viewId > 0) {
    $tStmt = $pdo->prepare('SELECT t.*, u.username FROM support_tickets t INNER JOIN users u ON u.id = t.user_id WHERE t.id = ? LIMIT 1');
    $tStmt->execute([$viewId]);
    $activeTicket = $tStmt->fetch();
    if ($activeTicket) {
        $mStmt = $pdo->prepare('SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY id ASC');
        $mStmt->execute([$viewId]);
        $messages = $mStmt->fetchAll();
    }
}

$pageTitle = 'Support Tickets';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="mb-6">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Support Desk</h1>
    <p class="text-sm text-slate-500 mt-0.5">Respond to customer inquiries and resolve order issues.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
        <div class="divide-y divide-slate-100 max-h-[600px] overflow-y-auto">
            <?php if (empty($tickets)): ?>
                <div class="p-10 text-center text-sm text-slate-500">No support tickets yet.</div>
            <?php else: ?>
                <?php foreach ($tickets as $t): ?>
                    <a href="/admin/tickets.php?view=<?= (int)$t['id'] ?>"
                       class="block px-4 py-3.5 hover:bg-slate-50 <?= $viewId === (int)$t['id'] ? 'bg-[#F8F6FF]' : '' ?>">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-xs text-[#151515] truncate"><?= e($t['subject']) ?></span>
                            <span class="text-[11px] font-semibold px-2 py-0.5 rounded bg-slate-100"><?= e(ucfirst($t['status'])) ?></span>
                        </div>
                        <div class="text-xs text-slate-500 mt-1">#<?= e($t['ticket_code']) ?> · <?= e($t['username']) ?> · <?= e(time_ago($t['last_reply_at'] ?: $t['created_at'])) ?></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
        <?php if (!$activeTicket): ?>
            <div class="py-12 text-center text-sm text-slate-500">Select a ticket to inspect messages and reply.</div>
        <?php else: ?>
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                <div>
                    <h2 class="text-base font-bold text-[#151515]"><?= e($activeTicket['subject']) ?></h2>
                    <div class="text-xs text-slate-500">#<?= e($activeTicket['ticket_code']) ?> · <?= e($activeTicket['username']) ?></div>
                </div>
                <?php if ($activeTicket['status'] !== 'closed'): ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ticket_id" value="<?= (int)$activeTicket['id'] ?>">
                        <button type="submit" name="action" value="close" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50">Close Ticket</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="space-y-3 mb-5 max-h-80 overflow-y-auto">
                <?php foreach ($messages as $m): ?>
                    <div class="p-3.5 rounded-xl border text-xs <?= $m['sender_type'] === 'admin' ? 'bg-[#F8F6FF] border-[#7138D8]/20' : 'bg-slate-50 border-slate-200' ?>">
                        <div class="flex justify-between font-bold mb-1">
                            <span><?= $m['sender_type'] === 'admin' ? 'Admin Reply' : e($activeTicket['username']) ?></span>
                            <span class="text-slate-400 font-normal"><?= e(date('d M, H:i', strtotime($m['created_at']))) ?></span>
                        </div>
                        <div class="text-slate-700 whitespace-pre-line"><?= e($m['message']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($activeTicket['status'] !== 'closed'): ?>
                <form method="post" class="space-y-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="ticket_id" value="<?= (int)$activeTicket['id'] ?>">
                    <textarea name="message" rows="3" required placeholder="Write staff reply..." class="w-full p-3 rounded-xl border border-slate-200 text-sm"></textarea>
                    <button type="submit" name="action" value="reply" class="h-10 px-5 rounded-xl bg-[#7138D8] text-white text-xs font-semibold">Send Reply</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
