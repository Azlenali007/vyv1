<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('payments.manage');
$pdo   = Database::getConnection();

$rows = $pdo->query(
    'SELECT wt.*, u.username
     FROM wallet_transactions wt
     INNER JOIN users u ON u.id = wt.user_id
     ORDER BY wt.id DESC LIMIT 150'
)->fetchAll();

$pageTitle = 'Transactions';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="mb-6">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Global Wallet Ledger</h1>
    <p class="text-sm text-slate-500 mt-0.5">All credits, order charges, refunds, and manual adjustments across users.</p>
</div>

<div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3.5">TX Code</th>
                    <th class="px-4 py-3.5">User</th>
                    <th class="px-4 py-3.5">Type</th>
                    <th class="px-4 py-3.5">Description</th>
                    <th class="px-4 py-3.5 text-right">Amount</th>
                    <th class="px-4 py-3.5 text-right">After</th>
                    <th class="px-4 py-3.5 text-right">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-slate-500">No wallet transactions recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r):
                        $isCr = in_array($r['type'], ['deposit', 'refund', 'admin_credit', 'bonus'], true);
                    ?>
                        <tr class="table-row-hover">
                            <td class="px-4 py-3 font-mono-code text-xs font-semibold">#<?= e($r['tx_code']) ?></td>
                            <td class="px-4 py-3 text-xs font-semibold text-[#7138D8]"><a href="/admin/user-details.php?id=<?= (int)$r['user_id'] ?>"><?= e($r['username']) ?></a></td>
                            <td class="px-4 py-3 text-xs"><?= e(ucwords(str_replace('_', ' ', $r['type']))) ?></td>
                            <td class="px-4 py-3 text-xs text-slate-700"><?= e($r['description']) ?></td>
                            <td class="px-4 py-3 text-right font-bold tabular-nums <?= $isCr ? 'text-[#16A36A]' : 'text-rose-600' ?>"><?= $isCr ? '+' : '-' ?><?= e(format_money($r['amount'])) ?></td>
                            <td class="px-4 py-3 text-right text-xs tabular-nums text-slate-600"><?= e(format_money($r['balance_after'])) ?></td>
                            <td class="px-4 py-3 text-right text-xs text-slate-400 whitespace-nowrap"><?= e(date('d M, H:i', strtotime($r['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
