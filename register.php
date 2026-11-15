<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$registrationEnabled = get_setting('registration_enabled', '1') === '1';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    if (!$registrationEnabled) {
        $error = 'New user registrations are currently paused by the administrator.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['password_confirm'] ?? '');
        if ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $res = Auth::registerUser(
                (string)($_POST['username'] ?? ''),
                (string)($_POST['full_name'] ?? ''),
                (string)($_POST['email'] ?? ''),
                $password
            );
            if ($res['ok']) {
                set_flash('success', 'Account created successfully.');
                redirect('/dashboard.php');
            }
            $error = $res['error'];
        }
    }
}

$siteName = get_setting('site_name', APP_NAME_DEFAULT);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account · <?= e($siteName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-[#F6F7FB] text-[#151515] min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-3xl border border-slate-200/80 shadow-xl shadow-slate-900/5 p-7 sm:p-9">
        <a href="/index.php" class="inline-flex items-center gap-2.5 mb-6">
            <span class="w-9 h-9 rounded-xl bg-[#7138D8] flex items-center justify-center text-white">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm0 2h14v2H5v-2z"/></svg>
            </span>
            <span class="text-lg font-bold text-[#151515]"><?= e($siteName) ?></span>
        </a>

        <h1 class="text-2xl font-bold text-[#151515]">Create your account</h1>
        <p class="text-sm text-slate-500 mt-1">Start placing orders and managing campaigns.</p>

        <?php if (!$registrationEnabled): ?>
            <div class="mt-4 px-4 py-3 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-800">
                Public registration is currently disabled by the panel administrator.
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="mt-4 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-700 font-medium">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="mt-6 space-y-4">
            <?= csrf_field() ?>
            <div>
                <label for="full_name" class="block text-xs font-semibold text-slate-700 mb-1.5">Full Name</label>
                <input id="full_name" type="text" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>"
                       class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8] focus:ring-2 focus:ring-[#7138D8]/15">
            </div>
            <div>
                <label for="username" class="block text-xs font-semibold text-slate-700 mb-1.5">Username</label>
                <input id="username" type="text" name="username" required value="<?= e($_POST['username'] ?? '') ?>"
                       class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8] focus:ring-2 focus:ring-[#7138D8]/15">
            </div>
            <div>
                <label for="email" class="block text-xs font-semibold text-slate-700 mb-1.5">Email Address</label>
                <input id="email" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"
                       class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8] focus:ring-2 focus:ring-[#7138D8]/15">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="password" class="block text-xs font-semibold text-slate-700 mb-1.5">Password</label>
                    <input id="password" type="password" name="password" required
                           class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8] focus:ring-2 focus:ring-[#7138D8]/15">
                </div>
                <div>
                    <label for="password_confirm" class="block text-xs font-semibold text-slate-700 mb-1.5">Confirm</label>
                    <input id="password_confirm" type="password" name="password_confirm" required
                           class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8] focus:ring-2 focus:ring-[#7138D8]/15">
                </div>
            </div>
            <button type="submit" <?= !$registrationEnabled ? 'disabled' : '' ?>
                    class="w-full h-11 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] disabled:opacity-50 text-white text-sm font-semibold smooth-transition">
                Create Account
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-slate-500">
            Already have an account? <a href="/login.php" class="font-semibold text-[#7138D8] hover:underline">Sign in</a>
        </p>
    </div>
</body>
</html>
