<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('payments.manage');
$pdo   = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $paymentId = (int)($_POST['payment_id'] ?? 0);
    $action    = $_POST['action'] ?? '';
    $note      = (string)($_POST['admin_note'] ?? '');

    if ($action === 'approve') {
        $res = Payment::approve($paymentId, (int)$admin['id'], $note);
        set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Deposit approved & wallet credited (' . format_money($res['credited']) . ').' : $res['error']);
    } elseif ($action === 'reject') {
        $res = Payment::reject($paymentId, (int)$admin['id'], $note);
        set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Deposit request rejected.' : $res['error']);
    }
    redirect('/admin/payments.php');
}

$status = $_GET['status'] ?? 'all';
$where = in_array($status, ['pending', 'approved', 'rejected'], true) ? 'WHERE p.status = ?' : '';
$stmt = $pdo->prepare(
    "SELECT p.*, u.username, pm.name AS method_name
     FROM payments p
     INNER JOIN users u ON u.id = p.user_id
     INNER JOIN payment_methods pm ON pm.id = p.method_id
     {$where}
     ORDER BY p.id DESC LIMIT 100"
);
$stmt->execute($where ? [$status] : []);
$payments = $stmt->fetchAll();

$pageTitle = 'Payments';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Payment Verification</h1>
        <p class="text-sm text-slate-500 mt-0.5">Approve or reject add-funds requests. Approved payments credit wallets atomically.</p>
    </div>
    <div class="flex gap-2 text-xs font-semibold">
        <a href="/admin/payments.php" class="px-3 py-1.5 rounded-xl border <?= $status === 'all' ? 'bg-[#151515] text-white border-[#151515]' : 'bg-white border-slate-200' ?>">All</a>
        <a href="/admin/payments.php?status=pending" class="px-3 py-1.5 rounded-xl border <?= $status === 'pending' ? 'bg-[#7138D8] text-white border-[#7138D8]' : 'bg-white border-slate-200' ?>">Pending</a>
        <a href="/admin/payments.php?status=approved" class="px-3 py-1.5 rounded-xl border <?= $status === 'approved' ? 'bg-[#16A36A] text-white border-[#16A36A]' : 'bg-white border-slate-200' ?>">Approved</a>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3.5">Code</th>
                    <th class="px-4 py-3.5">User & Method</th>
                    <th class="px-4 py-3.5">TX Reference</th>
                    <th class="px-4 py-3.5 text-right">Net Credit</th>
                    <th class="px-4 py-3.5">Status</th>
                    <th class="px-4 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($payments)): ?>
                    <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">No payment requests found.</td></tr>
                <?php else: ?>
                    <?php foreach ($payments as $p):
                        $stCls = match ($p['status']) {
                            'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                            default    => 'bg-amber-50 text-amber-700 border-amber-200',
                        };
                    ?>
                        <tr class="table-row-hover">
                            <td class="px-4 py-3.5 font-mono-code text-xs font-semibold">#<?= e($p['payment_code']) ?></td>
                            <td class="px-4 py-3.5">
                                <div class="font-semibold text-xs text-[#151515]"><?= e($p['username']) ?></div>
                                <div class="text-xs text-slate-500"><?= e($p['method_name']) ?></div>
                            </td>
                            <td class="px-4 py-3.5 font-mono-code text-xs">
                                <?= e($p['transaction_ref']) ?>
                                <?php if (!empty($p['proof_file'])): ?>
                                    <div><a href="/uploads/payments/<?= e($p['proof_file']) ?>" target="_blank" class="text-[#7138D8] font-sans font-semibold hover:underline">View Receipt</a></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3.5 text-right font-bold tabular-nums text-[#16A36A]"><?= e(format_money($p['net_credit'])) ?></td>
                            <td class="px-4 py-3.5"><span class="px-2.5 py-0.5 rounded-lg border text-xs font-semibold <?= $stCls ?>"><?= e(ucfirst($p['status'])) ?></span></td>
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <?php if ($p['status'] === 'pending'): ?>
                                    <form method="post" class="inline-flex gap-1.5">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                                        <button type="submit" name="action" value="approve" class="px-3 py-1.5 rounded-lg bg-[#16A36A] text-white text-xs font-semibold">Approve</button>
                                        <button type="submit" name="action" value="reject" class="px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-semibold">Reject</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">Processed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
