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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $fullName = clean_text($_POST['full_name'] ?? '', 120);
    $email    = strtolower(trim((string)($_POST['email'] ?? '')));
    $status   = in_array($_POST['status'] ?? '', ['active', 'suspended', 'banned'], true) ? $_POST['status'] : 'active';
    $apiStatus = !empty($_POST['api_status']) ? 1 : 0;

    if ($fullName === '' || !validate_email($email)) {
        $error = 'Valid full name and email are required.';
    } else {
        $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, status = ?, api_status = ? WHERE id = ?');
        $stmt->execute([$fullName, $email, $status, $apiStatus, $id]);

        if (!empty($_POST['new_password'])) {
            if ($err = validate_password((string)$_POST['new_password'])) {
                $error = $err;
            } else {
                $hash = password_hash((string)$_POST['new_password'], PASSWORD_BCRYPT);
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
            }
        }

        if ($error === '') {
            log_activity('admin', (int)$admin['id'], 'admin.user_edit', 'user', $id, "Updated user {$user['username']}");
            set_flash('success', 'User updated.');
            redirect('/admin/user-details.php?id=' . $id);
        }
    }
}

$pageTitle = 'Edit User · ' . $user['username'];
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="max-w-xl">
    <a href="/admin/user-details.php?id=<?= $id ?>" class="text-xs font-semibold text-[#7138D8] hover:underline">&larr; Back to User Details</a>
    <h1 class="text-xl font-bold text-[#151515] mt-1 mb-5">Edit User (@<?= e($user['username']) ?>)</h1>

    <?php if ($error !== ''): ?>
        <div class="mb-4 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-700 font-medium"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-slate-200/80 p-6">
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Full Name</label>
                <input type="text" name="full_name" required value="<?= e($user['full_name']) ?>" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Email Address</label>
                <input type="email" name="email" required value="<?= e($user['email']) ?>" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Account Status</label>
                <select name="status" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm bg-white">
                    <?php foreach (['active', 'suspended', 'banned'] as $st): ?>
                        <option value="<?= e($st) ?>" <?= $user['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Reset Password (Leave blank to keep current)</label>
                <input type="password" name="new_password" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm">
            </div>
            <label class="flex items-center gap-2 text-xs font-medium text-slate-700">
                <input type="checkbox" name="api_status" value="1" <?= (int)$user['api_status'] === 1 ? 'checked' : '' ?> class="rounded">
                Allow REST API access for this user
            </label>
            <button type="submit" class="h-11 px-6 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-sm font-semibold">Save Changes</button>
        </form>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
