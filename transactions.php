<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$user = require_user_login();
$transactions = Wallet::getTransactionsForUser((int)$user['id'], 100, 0);

$pageTitle = 'Transactions';
include INCLUDES_PATH . '/user-header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Wallet Transactions</h1>
        <p class="text-sm text-slate-500 mt-0.5">Complete immutable ledger of all deposits, order charges, refunds, and adjustments.</p>
    </div>
    <div class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-sm">
        Current Balance: <strong class="text-[#16A36A] tabular-nums"><?= e(format_money($user['balance'])) ?></strong>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3.5">TX Code</th>
                    <th class="px-4 py-3.5">Type</th>
                    <th class="px-4 py-3.5">Description</th>
                    <th class="px-4 py-3.5 text-right">Amount</th>
                    <th class="px-4 py-3.5 text-right">Before &rarr; After</th>
                    <th class="px-4 py-3.5 text-right">Timestamp</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($transactions)): ?>
                    <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">No wallet transactions recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($transactions as $tx):
                        $isCredit = in_array($tx['type'], ['deposit', 'refund', 'admin_credit', 'bonus'], true);
                    ?>
                        <tr class="table-row-hover">
                            <td class="px-4 py-3.5 font-mono-code text-xs font-semibold text-slate-700">#<?= e($tx['tx_code']) ?></td>
                            <td class="px-4 py-3.5 text-xs font-semibold text-slate-700"><?= e(ucwords(str_replace('_', ' ', $tx['type']))) ?></td>
                            <td class="px-4 py-3.5 text-slate-700"><?= e($tx['description']) ?></td>
                            <td class="px-4 py-3.5 text-right font-bold tabular-nums <?= $isCredit ? 'text-[#16A36A]' : 'text-rose-600' ?>">
                                <?= $isCredit ? '+' : '-' ?><?= e(format_money($tx['amount'])) ?>
                            </td>
                            <td class="px-4 py-3.5 text-right text-xs text-slate-500 tabular-nums">
                                <?= e(format_money($tx['balance_before'])) ?> &rarr; <?= e(format_money($tx['balance_after'])) ?>
                            </td>
                            <td class="px-4 py-3.5 text-right text-xs text-slate-500 whitespace-nowrap"><?= e(date('d M Y, H:i', strtotime($tx['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include INCLUDES_PATH . '/user-footer.php'; ?>
