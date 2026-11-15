<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('users.manage');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $res = User::createByAdmin($_POST, (int)$admin['id']);
    if ($res['ok']) {
        set_flash('success', 'User created successfully.');
        redirect('/admin/user-details.php?id=' . (int)$res['user_id']);
    }
    $error = $res['error'];
}

$pageTitle = 'Add User';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="max-w-xl">
    <a href="/admin/users.php" class="text-xs font-semibold text-[#7138D8] hover:underline">&larr; Back to Users</a>
    <h1 class="text-xl font-bold text-[#151515] mt-1 mb-5">Create New User</h1>

    <?php if ($error !== ''): ?>
        <div class="mb-4 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-700 font-medium"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-slate-200/80 p-6">
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Username</label>
                    <input type="text" name="username" required value="<?= e($_POST['username'] ?? '') ?>" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Full Name</label>
                    <input type="text" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Email Address</label>
                <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Password</label>
                    <input type="password" name="password" required class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Initial Balance</label>
                    <input type="number" step="0.01" min="0" name="balance" value="<?= e($_POST['balance'] ?? '0') ?>" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm tabular-nums">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Status</label>
                <select name="status" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm bg-white">
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="banned">Banned</option>
                </select>
            </div>
            <button type="submit" class="h-11 px-6 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-sm font-semibold">Create User</button>
        </form>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
