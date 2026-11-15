<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('users.manage');
$pdo   = Database::getConnection();

$q = trim((string)($_GET['q'] ?? ''));
$params = [];
$where = '';
if ($q !== '') {
    $where = 'WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}

$stmt = $pdo->prepare("SELECT * FROM users {$where} ORDER BY id DESC LIMIT 100");
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Users';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">User Management</h1>
        <p class="text-sm text-slate-500 mt-0.5">Manage registered customers, account statuses, and wallet balances.</p>
    </div>
    <a href="/admin/user-add.php" class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-xs font-semibold whitespace-nowrap">
        + Add User
    </a>
</div>

<form method="get" class="bg-white rounded-2xl border border-slate-200/80 p-4 mb-5 flex gap-3">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by username, full name, or email..."
           class="flex-1 h-10 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]">
    <button type="submit" class="h-10 px-5 rounded-xl bg-[#151515] text-white text-xs font-semibold">Search</button>
</form>

<div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3.5">ID</th>
                    <th class="px-4 py-3.5">User</th>
                    <th class="px-4 py-3.5 text-right">Balance</th>
                    <th class="px-4 py-3.5 text-right">Total Spent</th>
                    <th class="px-4 py-3.5">Status</th>
                    <th class="px-4 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($users)): ?>
                    <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">No users registered yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u):
                        $stCls = match ($u['status']) {
                            'active'    => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'suspended' => 'bg-amber-50 text-amber-700 border-amber-200',
                            default     => 'bg-rose-50 text-rose-700 border-rose-200',
                        };
                    ?>
                        <tr class="table-row-hover">
                            <td class="px-4 py-3.5 font-mono-code text-xs text-slate-500">#<?= (int)$u['id'] ?></td>
                            <td class="px-4 py-3.5">
                                <div class="font-semibold text-[#151515]"><?= e($u['full_name']) ?> <span class="text-xs font-normal text-slate-500">(@<?= e($u['username']) ?>)</span></div>
                                <div class="text-xs text-slate-500"><?= e($u['email']) ?></div>
                            </td>
                            <td class="px-4 py-3.5 text-right font-bold text-[#16A36A] tabular-nums"><?= e(format_money($u['balance'])) ?></td>
                            <td class="px-4 py-3.5 text-right text-slate-700 tabular-nums"><?= e(format_money($u['total_spent'])) ?></td>
                            <td class="px-4 py-3.5"><span class="px-2.5 py-0.5 rounded-lg border text-xs font-semibold <?= $stCls ?>"><?= e(ucfirst($u['status'])) ?></span></td>
                            <td class="px-4 py-3.5 text-right space-x-2 whitespace-nowrap">
                                <a href="/admin/user-details.php?id=<?= (int)$u['id'] ?>" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-700 hover:bg-slate-50">Details</a>
                                <a href="/admin/user-edit.php?id=<?= (int)$u['id'] ?>" class="px-2.5 py-1.5 rounded-lg bg-[#7138D8]/10 text-[#7138D8] text-xs font-semibold hover:bg-[#7138D8]/20">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
