<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Admin Dashboard';
$siteName  = get_setting('site_name', APP_NAME_DEFAULT);
$authAdmin = current_admin();
$flashes   = get_flashes();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> · Admin · <?= e($siteName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body class="bg-[#F6F7FB] text-[#151515] min-h-screen antialiased" x-data="{ mobileMenuOpen: false }">
<div class="flex min-h-screen">
    <?php include INCLUDES_PATH . '/admin-sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 lg:pl-[260px]">
        <header class="sticky top-0 z-30 h-16 bg-white border-b border-slate-200/80 px-4 sm:px-6 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <button type="button" @click="mobileMenuOpen = !mobileMenuOpen"
                        class="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-600 hover:bg-slate-100"
                        aria-label="Toggle admin menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <div class="flex items-center gap-2 text-sm">
                    <span class="text-slate-400">Admin</span>
                    <span class="text-slate-300">/</span>
                    <span class="font-semibold text-[#151515] truncate"><?= e($pageTitle) ?></span>
                </div>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <a href="/index.php" target="_blank"
                   class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-700 hover:bg-slate-50 whitespace-nowrap">
                    View Storefront
                </a>
                <?php if ($authAdmin): ?>
                    <div class="flex items-center gap-2.5 pl-2">
                        <span class="w-8 h-8 rounded-full bg-[#7138D8] text-white text-xs font-bold flex items-center justify-center">
                            <?= e(strtoupper(substr($authAdmin['username'], 0, 2))) ?>
                        </span>
                        <div class="hidden sm:block text-left">
                            <div class="text-xs font-semibold text-[#151515] leading-tight"><?= e($authAdmin['full_name']) ?></div>
                            <div class="text-[11px] text-slate-500 leading-tight"><?= e($authAdmin['role_name']) ?></div>
                        </div>
                        <a href="/admin/login.php?logout=1" class="ml-2 text-xs font-semibold text-rose-600 hover:text-rose-700 whitespace-nowrap">Sign Out</a>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6 max-w-[1440px] w-full mx-auto">
            <?php if (!empty($flashes)): ?>
                <div class="space-y-2 mb-5">
                    <?php foreach ($flashes as $f): ?>
                        <?php
                        $isErr = ($f['type'] ?? '') === 'error';
                        $cls = $isErr ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800';
                        ?>
                        <div class="px-4 py-3 rounded-xl border text-sm font-medium flex items-center justify-between gap-3 <?= $cls ?>">
                            <span><?= e($f['message']) ?></span>
                            <button type="button" onclick="this.parentElement.remove()" class="text-xs opacity-60 hover:opacity-100">Dismiss</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
