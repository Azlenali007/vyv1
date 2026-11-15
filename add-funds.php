<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$user = require_user_login();
$pdo  = Database::getConnection();
$methods = Payment::activeMethods();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $res = Payment::submitRequest(
        (int)$user['id'],
        (int)($_POST['method_id'] ?? 0),
        (float)($_POST['amount'] ?? 0),
        (string)($_POST['transaction_ref'] ?? ''),
        (string)($_POST['user_note'] ?? ''),
        $_FILES['proof_file'] ?? null
    );
    if ($res['ok']) {
        set_flash('success', "Deposit request #{$res['payment_code']} submitted for admin verification.");
        redirect('/add-funds.php');
    }
    $error = $res['error'];
}

$historyStmt = $pdo->prepare(
    'SELECT p.*, pm.name AS method_name
     FROM payments p
     INNER JOIN payment_methods pm ON pm.id = p.method_id
     WHERE p.user_id = ?
     ORDER BY p.id DESC LIMIT 30'
);
$historyStmt->execute([(int)$user['id']]);
$payments = $historyStmt->fetchAll();

$pageTitle = 'Add Funds';
include INCLUDES_PATH . '/user-header.php';
?>

<div class="mb-6">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Add Funds</h1>
    <p class="text-sm text-slate-500 mt-0.5">Submit a payment request with your transaction reference. Balances are credited upon verification.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="mb-5 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-700 font-medium"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6"
     x-data="{
        methods: <?= e(json_encode(array_map(fn($m) => [
            'id' => (int)$m['id'],
            'name' => $m['name'],
            'instructions' => $m['instructions'],
            'min' => (float)$m['min_amount'],
            'max' => (float)$m['max_amount'],
            'fee' => (float)$m['fee_percent'],
            'bonus' => (float)$m['bonus_percent'],
        ], $methods))) ?>,
        selectedId: <?= !empty($methods) ? (int)$methods[0]['id'] : 0 ?>,
        get current() {
            return this.methods.find(m => m.id === Number(this.selectedId)) || null;
        }
     }">
    <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
        <?php if (empty($methods)): ?>
            <div class="py-10 text-center text-sm text-slate-500">No payment methods are currently active. Please contact support.</div>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data" class="space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label for="method_id" class="block text-xs font-semibold text-slate-700 mb-1.5">Payment Method</label>
                    <select id="method_id" name="method_id" x-model.number="selectedId" required
                            class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm bg-white focus:outline-none focus:border-[#7138D8]">
                        <?php foreach ($methods as $m): ?>
                            <option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="amount" class="block text-xs font-semibold text-slate-700 mb-1.5">Amount</label>
                    <input id="amount" type="number" step="0.01" name="amount" required
                           :min="current ? current.min : 1" :max="current ? current.max : 1000000"
                           value="<?= e($_POST['amount'] ?? '') ?>"
                           class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm tabular-nums focus:outline-none focus:border-[#7138D8]">
                    <div class="mt-1 text-xs text-slate-500" x-show="current">
                        Allowed: <span x-text="current?.min"></span> – <span x-text="current?.max"></span>
                        <template x-if="current && current.bonus > 0">
                            <span class="text-[#16A36A] font-semibold"> · +<span x-text="current.bonus"></span>% Bonus</span>
                        </template>
                    </div>
                </div>

                <div>
                    <label for="transaction_ref" class="block text-xs font-semibold text-slate-700 mb-1.5">Transaction / UTR Reference ID</label>
                    <input id="transaction_ref" type="text" name="transaction_ref" required value="<?= e($_POST['transaction_ref'] ?? '') ?>"
                           placeholder="Enter bank UTR, UPI ref, or TX hash"
                           class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]">
                </div>

                <div>
                    <label for="proof_file" class="block text-xs font-semibold text-slate-700 mb-1.5">Payment Receipt / Screenshot (Optional, max 5 MB)</label>
                    <input id="proof_file" type="file" name="proof_file" accept="image/*"
                           class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-[#7138D8]/10 file:text-[#7138D8] hover:file:bg-[#7138D8]/20">
                </div>

                <div>
                    <label for="user_note" class="block text-xs font-semibold text-slate-700 mb-1.5">Note (Optional)</label>
                    <textarea id="user_note" name="user_note" rows="2"
                              class="w-full p-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]"><?= e($_POST['user_note'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="h-11 px-6 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-sm font-semibold smooth-transition">
                    Submit Deposit Request
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="lg:col-span-5 space-y-4">
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
            <div class="text-xs font-semibold text-slate-500">Current Balance</div>
            <div class="text-2xl font-bold text-[#16A36A] tabular-nums mt-1"><?= e(format_money($user['balance'])) ?></div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6" x-show="current">
            <h2 class="text-sm font-bold text-[#151515]" x-text="current?.name"></h2>
            <p class="mt-2 text-xs text-slate-600 whitespace-pre-line leading-relaxed" x-text="current?.instructions"></p>
        </div>
    </div>
</div>

<!-- Deposit Request History -->
<div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100">
        <h2 class="text-base font-bold text-[#151515]">Recent Deposit Requests</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Method</th>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($payments)): ?>
                    <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">No deposit requests submitted yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($payments as $p):
                        $stCls = match ($p['status']) {
                            'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                            default    => 'bg-amber-50 text-amber-700 border-amber-200',
                        };
                    ?>
                        <tr class="table-row-hover">
                            <td class="px-4 py-3 font-mono-code text-xs font-semibold">#<?= e($p['payment_code']) ?></td>
                            <td class="px-4 py-3 text-slate-700"><?= e($p['method_name']) ?></td>
                            <td class="px-4 py-3 font-mono-code text-xs text-slate-600"><?= e($p['transaction_ref']) ?></td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums"><?= e(format_money($p['net_credit'])) ?></td>
                            <td class="px-4 py-3"><span class="inline-flex px-2.5 py-0.5 rounded-lg border text-xs font-semibold <?= $stCls ?>"><?= e(ucfirst($p['status'])) ?></span></td>
                            <td class="px-4 py-3 text-right text-xs text-slate-500"><?= e(date('d M Y, H:i', strtotime($p['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include INCLUDES_PATH . '/user-footer.php'; ?>
