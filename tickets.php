<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$user = require_user_login();
$pdo  = Database::getConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $res = Ticket::create(
        (int)$user['id'],
        (string)($_POST['subject'] ?? ''),
        (string)($_POST['department'] ?? 'general'),
        (string)($_POST['priority'] ?? 'medium'),
        (string)($_POST['message'] ?? ''),
        !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null,
        $_FILES['attachment'] ?? null
    );
    if ($res['ok']) {
        set_flash('success', "Support ticket #{$res['ticket_code']} created.");
        redirect('/ticket-details.php?id=' . (int)$res['ticket_id']);
    }
    $error = $res['error'];
}

$tStmt = $pdo->prepare('SELECT * FROM support_tickets WHERE user_id = ? ORDER BY id DESC');
$tStmt->execute([(int)$user['id']]);
$tickets = $tStmt->fetchAll();

$oStmt = $pdo->prepare('SELECT id, order_code FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 30');
$oStmt->execute([(int)$user['id']]);
$userOrders = $oStmt->fetchAll();

$prefillOrderId = (int)($_GET['order_id'] ?? 0);

$pageTitle = 'Support Tickets';
include INCLUDES_PATH . '/user-header.php';
?>

<div class="mb-6">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Support Tickets</h1>
    <p class="text-sm text-slate-500 mt-0.5">Open a ticket with our support team for order, payment, or API assistance.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="mb-5 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-700 font-medium"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
        <h2 class="text-base font-bold text-[#151515] mb-4">New Support Ticket</h2>
        <form method="post" enctype="multipart/form-data" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Subject</label>
                <input type="text" name="subject" required value="<?= e($_POST['subject'] ?? '') ?>"
                       class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Department</label>
                    <select name="department" class="w-full h-11 px-3 rounded-xl border border-slate-200 text-sm bg-white">
                        <option value="order">Orders</option>
                        <option value="payment">Payments</option>
                        <option value="api">API</option>
                        <option value="general">General</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Priority</label>
                    <select name="priority" class="w-full h-11 px-3 rounded-xl border border-slate-200 text-sm bg-white">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
            </div>
            <?php if (!empty($userOrders)): ?>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Related Order (Optional)</label>
                <select name="order_id" class="w-full h-11 px-3 rounded-xl border border-slate-200 text-sm bg-white">
                    <option value="">None</option>
                    <?php foreach ($userOrders as $o): ?>
                        <option value="<?= (int)$o['id'] ?>" <?= $prefillOrderId === (int)$o['id'] ? 'selected' : '' ?>>#<?= e($o['order_code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Message</label>
                <textarea name="message" rows="4" required class="w-full p-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]"><?= e($_POST['message'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Attachment (Optional)</label>
                <input type="file" name="attachment" class="w-full text-xs text-slate-600">
            </div>
            <button type="submit" class="w-full h-11 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-sm font-semibold smooth-transition">
                Create Ticket
            </button>
        </form>
    </div>

    <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-base font-bold text-[#151515]">Your Tickets</h2>
        </div>
        <div class="divide-y divide-slate-100">
            <?php if (empty($tickets)): ?>
                <div class="p-10 text-center text-sm text-slate-500">You have not created any support tickets yet.</div>
            <?php else: ?>
                <?php foreach ($tickets as $t):
                    $stCls = match ($t['status']) {
                        'answered' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        'closed'   => 'bg-slate-100 text-slate-600 border-slate-200',
                        default    => 'bg-purple-50 text-purple-700 border-purple-200',
                    };
                ?>
                    <a href="/ticket-details.php?id=<?= (int)$t['id'] ?>" class="block px-5 py-4 hover:bg-slate-50 smooth-transition">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-semibold text-sm text-[#151515] truncate"><?= e($t['subject']) ?></div>
                            <span class="px-2.5 py-0.5 rounded-lg border text-xs font-semibold shrink-0 <?= $stCls ?>"><?= e(ucfirst($t['status'])) ?></span>
                        </div>
                        <div class="mt-1 text-xs text-slate-500 flex items-center gap-2">
                            <span class="font-mono-code">#<?= e($t['ticket_code']) ?></span>
                            <span>&middot;</span>
                            <span><?= e(ucfirst($t['department'])) ?></span>
                            <span>&middot;</span>
                            <span><?= e(time_ago($t['last_reply_at'] ?: $t['created_at'])) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include INCLUDES_PATH . '/user-footer.php'; ?>
