<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$user = require_user_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $res = User::updateProfile(
            (int)$user['id'],
            (string)($_POST['full_name'] ?? ''),
            (string)($_POST['email'] ?? ''),
            (string)($_POST['timezone'] ?? 'Asia/Kolkata')
        );
        set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Profile updated.' : $res['error']);
        redirect('/profile.php');
    }
    if ($action === 'change_password') {
        $newPwd = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if ($newPwd !== $confirm) {
            set_flash('error', 'New passwords do not match.');
        } else {
            $res = User::changePassword((int)$user['id'], (string)($_POST['current_password'] ?? ''), $newPwd);
            set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Password updated successfully.' : $res['error']);
        }
        redirect('/profile.php');
    }
}

$pageTitle = 'Account Settings';
include INCLUDES_PATH . '/user-header.php';
?>

<div class="mb-6">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Account Settings</h1>
    <p class="text-sm text-slate-500 mt-0.5">Manage your profile information and account password.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
        <h2 class="text-base font-bold text-[#151515] mb-4">Profile Details</h2>
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Username</label>
                <input type="text" value="<?= e($user['username']) ?>" disabled class="w-full h-11 px-3.5 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Full Name</label>
                <input type="text" name="full_name" required value="<?= e($user['full_name']) ?>" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Email Address</label>
                <input type="email" name="email" required value="<?= e($user['email']) ?>" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Timezone</label>
                <select name="timezone" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm bg-white">
                    <?php foreach (['Asia/Kolkata', 'UTC', 'America/New_York', 'Europe/London', 'Asia/Dubai', 'Asia/Singapore'] as $tz): ?>
                        <option value="<?= e($tz) ?>" <?= $user['timezone'] === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="h-11 px-6 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-sm font-semibold">Save Profile</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
        <h2 class="text-base font-bold text-[#151515] mb-4">Change Password</h2>
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Current Password</label>
                <input type="password" name="current_password" required class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">New Password</label>
                <input type="password" name="new_password" required class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Confirm New Password</label>
                <input type="password" name="confirm_password" required class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]">
            </div>
            <button type="submit" class="h-11 px-6 rounded-xl bg-[#151515] hover:bg-neutral-800 text-white text-sm font-semibold">Update Password</button>
        </form>
    </div>
</div>

<?php include INCLUDES_PATH . '/user-footer.php'; ?>
