<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('admins.manage');
$pdo   = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create_admin') {
        $username = trim((string)($_POST['username'] ?? ''));
        $fullName = clean_text($_POST['full_name'] ?? '', 120);
        $email    = strtolower(trim((string)($_POST['email'] ?? '')));
        $roleId   = (int)($_POST['role_id'] ?? 2);
        $password = (string)($_POST['password'] ?? '');

        if (!validate_username($username) || !validate_email($email) || $fullName === '') {
            set_flash('error', 'Valid username, full name, and email are required.');
        } elseif ($err = validate_password($password)) {
            set_flash('error', $err);
        } else {
            $dup = $pdo->prepare('SELECT id FROM admins WHERE username = ? OR email = ? LIMIT 1');
            $dup->execute([$username, $email]);
            if ($dup->fetch()) {
                set_flash('error', 'Admin username or email already exists.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare('INSERT INTO admins (role_id, username, full_name, email, password_hash, status) VALUES (?, ?, ?, ?, ?, "active")')
                    ->execute([$roleId, $username, $fullName, $email, $hash]);
                log_activity('admin', (int)$admin['id'], 'admin.create', 'admin', (int)$pdo->lastInsertId(), "Created admin {$username}");
                set_flash('success', 'Administrator created.');
            }
        }
    } elseif ($action === 'toggle_status') {
        $targetId = (int)($_POST['admin_id'] ?? 0);
        if ($targetId === (int)$admin['id']) {
            set_flash('error', 'You cannot deactivate your own active session.');
        } else {
            $newStatus = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
            $pdo->prepare('UPDATE admins SET status = ? WHERE id = ?')->execute([$newStatus, $targetId]);
            set_flash('success', 'Administrator status updated.');
        }
    }
    redirect('/admin/admins.php');
}

$admins = $pdo->query('SELECT a.*, r.name AS role_name FROM admins a INNER JOIN admin_roles r ON r.id = a.role_id ORDER BY a.id ASC')->fetchAll();
$roles  = $pdo->query('SELECT * FROM admin_roles ORDER BY id ASC')->fetchAll();

$pageTitle = 'Admins & Roles';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="mb-6">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Administrators & Role Permissions</h1>
    <p class="text-sm text-slate-500 mt-0.5">Manage staff accounts and role-based access control.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 p-6 h-fit">
        <h2 class="text-base font-bold text-[#151515] mb-4">Add Administrator</h2>
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_admin">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Full Name</label>
                <input type="text" name="full_name" required class="w-full h-10 px-3.5 rounded-xl border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Username</label>
                <input type="text" name="username" required class="w-full h-10 px-3.5 rounded-xl border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Email</label>
                <input type="email" name="email" required class="w-full h-10 px-3.5 rounded-xl border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Role</label>
                <select name="role_id" class="w-full h-10 px-3 rounded-xl border border-slate-200 text-sm bg-white">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Password</label>
                <input type="password" name="password" required class="w-full h-10 px-3.5 rounded-xl border border-slate-200 text-sm">
            </div>
            <button type="submit" class="w-full h-10 rounded-xl bg-[#7138D8] text-white text-xs font-semibold">Create Admin</button>
        </form>
    </div>

    <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
        <table class="w-full text-left border-collapse text-sm">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3.5">Admin</th>
                    <th class="px-4 py-3.5">Role</th>
                    <th class="px-4 py-3.5">Status</th>
                    <th class="px-4 py-3.5 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($admins as $a): ?>
                    <tr>
                        <td class="px-4 py-3.5">
                            <div class="font-semibold text-[#151515]"><?= e($a['full_name']) ?> (@<?= e($a['username']) ?>)</div>
                            <div class="text-xs text-slate-500"><?= e($a['email']) ?></div>
                        </td>
                        <td class="px-4 py-3.5 text-xs font-medium"><?= e($a['role_name']) ?></td>
                        <td class="px-4 py-3.5">
                            <span class="px-2 py-0.5 rounded-lg border text-xs font-semibold <?= $a['status'] === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200' ?>">
                                <?= e(ucfirst($a['status'])) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            <?php if ((int)$a['id'] !== (int)$admin['id']): ?>
                                <form method="post" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="admin_id" value="<?= (int)$a['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $a['status'] === 'active' ? 'inactive' : 'active' ?>">
                                    <button type="submit" class="text-xs font-semibold text-[#7138D8] hover:underline">
                                        <?= $a['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">Current</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
