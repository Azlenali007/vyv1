<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Dashboard';
$siteName  = get_setting('site_name', APP_NAME_DEFAULT);
$authUser  = current_user();
$unreadNotifications = $authUser ? Notification::countUnreadForUser((int)$authUser['id']) : 0;
$recentNotifications = $authUser ? Notification::getForUser((int)$authUser['id'], 6) : [];
$flashes = get_flashes();
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> · <?= e($siteName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            purple: '#7138D8',
                            purpleHover: '#5E28C2',
                            black: '#151515',
                            green: '#16A36A',
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    }
                }
            }
        };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body class="bg-[#F6F7FB] text-[#151515] min-h-screen antialiased" x-data="{ mobileMenuOpen: false }">
<div class="flex min-h-screen">
    <?php include INCLUDES_PATH . '/user-sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 lg:pl-[260px]">
        <!-- Top Navbar -->
        <header class="sticky top-0 z-30 h-16 bg-white border-b border-slate-200/80 px-4 sm:px-6 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <button type="button" @click="mobileMenuOpen = !mobileMenuOpen"
                        class="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-600 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#7138D8]"
                        aria-label="Toggle navigation menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <form action="/services.php" method="get" class="relative w-full max-w-md hidden sm:block">
                    <span class="absolute inset-y-0 left-3.5 flex items-center text-slate-400 pointer-events-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/>
                        </svg>
                    </span>
                    <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>"
                           placeholder="Search services..."
                           class="w-full h-10 pl-10 pr-4 text-sm bg-[#F6F7FB] border border-slate-200/80 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-[#7138D8] smooth-transition">
                </form>
            </div>

            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <?php if ($authUser): ?>
                    <a href="/new-order.php"
                       class="hidden md:inline-flex items-center gap-1.5 h-9 px-3.5 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-xs font-semibold whitespace-nowrap shrink-0 smooth-transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        New Order
                    </a>

                    <!-- Notifications Dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button type="button"
                                @click="open = !open; if(open) window.SMM.markNotificationsRead()"
                                class="relative inline-flex items-center justify-center w-10 h-10 rounded-xl border border-slate-200/80 bg-white text-slate-600 hover:text-[#151515] hover:bg-slate-50 smooth-transition"
                                aria-label="Notifications">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0h6z"/>
                            </svg>
                            <?php if ($unreadNotifications > 0): ?>
                                <span id="notif-badge-count"
                                      class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white text-[10px] font-bold flex items-center justify-center tabular-nums">
                                    <?= $unreadNotifications > 99 ? '99+' : $unreadNotifications ?>
                                </span>
                            <?php endif; ?>
                        </button>

                        <div x-show="open" x-cloak @click.outside="open = false"
                             class="absolute right-0 mt-2 w-80 bg-white rounded-2xl border border-slate-200 shadow-xl overflow-hidden z-50">
                            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                                <span class="text-sm font-semibold text-[#151515]">Notifications</span>
                                <span class="text-xs text-slate-400">Latest</span>
                            </div>
                            <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                                <?php if (empty($recentNotifications)): ?>
                                    <div class="px-4 py-6 text-center text-xs text-slate-500">No notifications yet.</div>
                                <?php else: ?>
                                    <?php foreach ($recentNotifications as $n): ?>
                                        <a href="<?= e($n['link'] ?: '#') ?>" class="block px-4 py-3 hover:bg-slate-50 smooth-transition">
                                            <div class="text-xs font-semibold text-slate-900"><?= e($n['title']) ?></div>
                                            <div class="text-xs text-slate-500 mt-0.5 line-clamp-2"><?= e($n['message']) ?></div>
                                            <div class="text-[11px] text-slate-400 mt-1"><?= e(time_ago($n['created_at'])) ?></div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Menu -->
                    <?php
                    $parts = preg_split('/\s+/', trim($authUser['full_name']));
                    $initials = strtoupper(substr($parts[0] ?? 'U', 0, 1) . substr($parts[1] ?? '', 0, 1));
                    ?>
                    <div class="relative" x-data="{ open: false }">
                        <button type="button" @click="open = !open"
                                class="flex items-center gap-2.5 pl-1.5 pr-2.5 py-1.5 rounded-xl hover:bg-slate-100 smooth-transition">
                            <span class="w-8 h-8 rounded-full bg-[#151515] text-white text-xs font-bold flex items-center justify-center shrink-0">
                                <?= e($initials) ?>
                            </span>
                            <span class="hidden sm:block text-sm font-semibold text-[#151515] whitespace-nowrap max-w-[130px] truncate">
                                <?= e($authUser['full_name']) ?>
                            </span>
                            <svg class="w-4 h-4 text-slate-400 hidden sm:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>

                        <div x-show="open" x-cloak @click.outside="open = false"
                             class="absolute right-0 mt-2 w-52 bg-white rounded-2xl border border-slate-200 shadow-xl py-1.5 z-50">
                            <div class="px-3.5 py-2 border-b border-slate-100">
                                <div class="text-xs font-semibold text-slate-900 truncate"><?= e($authUser['username']) ?></div>
                                <div class="text-xs text-[#16A36A] font-semibold tabular-nums mt-0.5">Balance: <?= e(format_money($authUser['balance'])) ?></div>
                            </div>
                            <a href="/profile.php" class="block px-3.5 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Account Settings</a>
                            <a href="/api-access.php" class="block px-3.5 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">API Access</a>
                            <a href="/transactions.php" class="block px-3.5 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Transactions</a>
                            <div class="border-t border-slate-100 mt-1 pt-1">
                                <a href="/logout.php" class="block px-3.5 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50">Sign Out</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/login.php" class="px-4 py-2 text-xs font-semibold text-slate-700 hover:text-[#151515] whitespace-nowrap">Sign In</a>
                    <a href="/register.php" class="px-4 py-2 text-xs font-semibold bg-[#7138D8] hover:bg-[#5E28C2] text-white rounded-xl whitespace-nowrap">Create Account</a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Main Viewport -->
        <main class="flex-1 p-4 sm:p-6 pb-24 lg:pb-8 max-w-[1440px] w-full mx-auto">
            <?php if (!empty($flashes)): ?>
                <div class="space-y-2 mb-5">
                    <?php foreach ($flashes as $f): ?>
                        <?php
                        $isErr = ($f['type'] ?? '') === 'error';
                        $cls = $isErr
                            ? 'bg-rose-50 border-rose-200 text-rose-800'
                            : 'bg-emerald-50 border-emerald-200 text-emerald-800';
                        ?>
                        <div class="px-4 py-3 rounded-xl border text-sm font-medium flex items-center justify-between gap-3 <?= $cls ?>">
                            <span><?= e($f['message']) ?></span>
                            <button type="button" onclick="this.parentElement.remove()" class="text-xs opacity-60 hover:opacity-100">Dismiss</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
