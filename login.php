<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $res = Auth::loginUser((string)($_POST['identifier'] ?? ''), (string)($_POST['password'] ?? ''));
    if ($res['ok']) {
        set_flash('success', 'Welcome back, ' . $res['user']['full_name'] . '.');
        redirect('/dashboard.php');
    }
    $error = $res['error'];
}

$siteName = get_setting('site_name', APP_NAME_DEFAULT);
$flashes  = get_flashes();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In · <?= e($siteName) ?></title>
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

        <h1 class="text-2xl font-bold text-[#151515]">Sign in to your account</h1>
        <p class="text-sm text-slate-500 mt-1">Manage campaigns, wallet balance, and live orders.</p>

        <?php foreach ($flashes as $f): ?>
            <div class="mt-4 px-4 py-3 rounded-xl border text-sm <?= ($f['type'] ?? '') === 'error' ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700' ?>">
                <?= e($f['message']) ?>
            </div>
        <?php endforeach; ?>

        <?php if ($error !== ''): ?>
            <div class="mt-4 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-700 font-medium">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="mt-6 space-y-4">
            <?= csrf_field() ?>
            <div>
                <label for="identifier" class="block text-xs font-semibold text-slate-700 mb-1.5">Email or Username</label>
                <input id="identifier" type="text" name="identifier" required autocomplete="username"
                       value="<?= e($_POST['identifier'] ?? '') ?>"
                       class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8] focus:ring-2 focus:ring-[#7138D8]/15">
            </div>
            <div>
                <label for="password" class="block text-xs font-semibold text-slate-700 mb-1.5">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password"
                       class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8] focus:ring-2 focus:ring-[#7138D8]/15">
            </div>
            <button type="submit" class="w-full h-11 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-sm font-semibold smooth-transition">
                Sign In
            </button>
        </form>

        <div class="mt-6 pt-5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
            <span>New here? <a href="/register.php" class="font-semibold text-[#7138D8] hover:underline">Create an account</a></span>
            <a href="/admin/login.php" class="hover:text-[#151515]">Admin Login</a>
        </div>
    </div>
</body>
</html>
