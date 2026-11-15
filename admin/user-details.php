<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('users.manage');
$pdo   = Database::getConnection();
$id    = (int)($_GET['id'] ?? 0);
$user  = User::find($id);

if (!$user) {
    set_flash('error', 'User not found.');
    redirect('/admin/users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'adjust_balance') {
    require_valid_csrf();
    require_admin_permission('users.balance');

    $op     = $_POST['operation'] === 'debit' ? 'debit' : 'credit';
    $amount = (float)($_POST['amount'] ?? 0);
    $reason = clean_text($_POST['reason'] ?? '', 250) ?: ('Manual admin ' . $op);

    $res = $op === 'credit'
        ? Wallet::credit($id, $amount, TX_TYPE_ADMIN_CREDIT, $reason, 'admin', (int)$admin['id'], (int)$admin['id'])
        : Wallet::debit($id, $amount, TX_TYPE_ADMIN_DEBIT, $reason, 'admin', (int)$admin['id'], (int)$admin['id']);

    if ($res['ok']) {
        log_activity('admin', (int)$admin['id'], "wallet.{$op}", 'user', $id, "{$op} {$amount}: {$reason}");
        set_flash('success', 'Wallet balance adjusted (' . format_money($res['balance_after']) . ').');
    } else {
        set_flash('error', $res['error']);
    }
    redirect('/admin/user-details.php?id=' . $id);
}

$txs = Wallet::getTransactionsForUser($id, 25, 0);
$ordStmt = $pdo->prepare('SELECT o.*, s.name AS service_name FROM orders o INNER JOIN services s ON s.id = o.service_id WHERE o.user_id = ? ORDER BY o.id DESC LIMIT 15');
$ordStmt->execute([$id]);
$orders = $ordStmt->fetchAll();

$pageTitle = 'User · ' . $user['username'];
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <a href="/admin/users.php" class="text-xs font-semibold text-[#7138D8] hover:underline">&larr; Back to Users</a>
        <h1 class="text-xl font-bold text-[#151515] mt-1"><?= e($user['full_name']) ?> <span class="text-sm font-normal text-slate-500">(@<?= e($user['username']) ?>)</span></h1>
    </div>
    <a href="/admin/user-edit.php?id=<?= $id ?>" class="h-10 px-4 inline-flex items-center rounded-xl bg-[#7138D8] text-white text-xs font-semibold">Edit User</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
    <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
        <div class="text-xs font-semibold text-slate-500">Current Wallet Balance</div>
        <div class="text-3xl font-bold text-[#16A36A] tabular-nums mt-1"><?= e(format_money($user['balance'])) ?></div>
        <div class="text-xs text-slate-500 mt-1">Total Spent: <strong class="text-[#151515] tabular-nums"><?= e(format_money($user['total_spent'])) ?></strong></div>

        <form method="post" class="mt-5 pt-5 border-t border-slate-100 space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="adjust_balance">
            <h2 class="text-sm font-bold text-[#151515]">Adjust Wallet Balance</h2>
            <div class="grid grid-cols-2 gap-3">
                <select name="operation" class="h-10 px-3 rounded-xl border border-slate-200 text-xs bg-white font-semibold">
                    <option value="credit">Add Funds (+ Credit)</option>
                    <option value="debit">Deduct Funds (- Debit)</option>
                </select>
                <input type="number" step="0.01" min="0.01" name="amount" required placeholder="Amount"
                       class="h-10 px-3 rounded-xl border border-slate-200 text-xs tabular-nums">
            </div>
            <input type="text" name="reason" required placeholder="Reason (logged in wallet_transactions)"
                   class="w-full h-10 px-3 rounded-xl border border-slate-200 text-xs">
            <button type="submit" class="w-full h-10 rounded-xl bg-[#151515] hover:bg-neutral-800 text-white text-xs font-semibold">Apply Balance Adjustment</button>
        </form>
    </div>

    <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
        <h2 class="text-base font-bold text-[#151515] mb-3">Recent Wallet Transactions</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-500">
                        <th class="py-2 pr-3">TX</th>
                        <th class="py-2 px-3">Description</th>
                        <th class="py-2 px-3 text-right">Amount</th>
                        <th class="py-2 pl-3 text-right">After</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($txs)): ?>
                        <tr><td colspan="4" class="py-8 text-center text-slate-500">No wallet transactions yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($txs as $tx):
                            $isCr = in_array($tx['type'], ['deposit', 'refund', 'admin_credit', 'bonus'], true);
                        ?>
                            <tr>
                                <td class="py-2.5 pr-3 font-mono-code">#<?= e($tx['tx_code']) ?></td>
                                <td class="py-2.5 px-3 text-slate-700"><?= e($tx['description']) ?></td>
                                <td class="py-2.5 px-3 text-right font-bold tabular-nums <?= $isCr ? 'text-[#16A36A]' : 'text-rose-600' ?>"><?= $isCr ? '+' : '-' ?><?= e(format_money($tx['amount'])) ?></td>
                                <td class="py-2.5 pl-3 text-right tabular-nums text-slate-600"><?= e(format_money($tx['balance_after'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
    <h2 class="text-base font-bold text-[#151515] mb-3">Recent Orders</h2>
    <?php if (empty($orders)): ?>
        <p class="text-sm text-slate-500">This user has not placed any orders.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-500">
                        <th class="py-2 pr-3">Order</th>
                        <th class="py-2 px-3">Service</th>
                        <th class="py-2 px-3 text-right">Qty</th>
                        <th class="py-2 px-3 text-right">Charge</th>
                        <th class="py-2 pl-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($orders as $o): $meta = order_status_meta($o['status']); ?>
                        <tr>
                            <td class="py-2.5 pr-3 font-mono-code font-semibold"><a href="/admin/order-details.php?id=<?= (int)$o['id'] ?>" class="text-[#7138D8] hover:underline">#<?= e($o['order_code']) ?></a></td>
                            <td class="py-2.5 px-3"><?= e($o['service_name']) ?></td>
                            <td class="py-2.5 px-3 text-right tabular-nums"><?= number_format((int)$o['quantity']) ?></td>
                            <td class="py-2.5 px-3 text-right font-semibold tabular-nums"><?= e(format_money($o['charge'])) ?></td>
                            <td class="py-2.5 pl-3"><span class="px-2 py-0.5 rounded-lg border font-semibold <?= $meta['bg'] ?>"><?= e($meta['label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
