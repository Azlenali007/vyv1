<?php
declare(strict_types=1);

$siteName    = get_setting('site_name', APP_NAME_DEFAULT);
$authUser    = current_user();
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');

$navItems = [
    ['file' => 'dashboard.php',    'label' => 'Dashboard',        'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    ['file' => 'new-order.php',    'label' => 'New Order',        'icon' => 'M12 4v16m8-8H4'],
    ['file' => 'bulk-order.php',   'label' => 'Bulk Orders',      'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
    ['file' => 'services.php',     'label' => 'Services',         'icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16'],
    ['file' => 'orders.php',       'label' => 'Orders',           'icon' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'],
    ['file' => 'add-funds.php',    'label' => 'Add Funds',        'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
    ['file' => 'transactions.php', 'label' => 'Transactions',     'icon' => 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z'],
    ['file' => 'tickets.php',      'label' => 'Support Tickets',  'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z'],
    ['file' => 'profile.php',      'label' => 'Account Settings', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
    ['file' => 'api-access.php',   'label' => 'API Access',       'icon' => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4'],
    ['file' => 'faq.php',          'label' => 'FAQ',              'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['file' => 'contact.php',      'label' => 'Contact Us',       'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
];
?>
<!-- Mobile Drawer Backdrop -->
<div x-show="mobileMenuOpen" x-cloak @click="mobileMenuOpen = false"
     class="fixed inset-0 bg-black/50 z-40 lg:hidden"></div>

<!-- Dark Obsidian Sidebar -->
<aside :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       class="fixed inset-y-0 left-0 z-50 w-[260px] bg-[#151515] text-white flex flex-col smooth-transition border-r border-white/5">
    <!-- Brand -->
    <div class="h-16 px-5 flex items-center justify-between border-b border-white/10 shrink-0">
        <a href="/dashboard.php" class="flex items-center gap-3 min-w-0">
            <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#7138D8] to-[#9333EA] flex items-center justify-center shrink-0 shadow-lg shadow-[#7138D8]/30">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm0 2h14v2H5v-2z"/>
                </svg>
            </span>
            <span class="text-base font-bold tracking-tight text-white truncate"><?= e($siteName) ?></span>
        </a>
        <button type="button" @click="mobileMenuOpen = false" class="lg:hidden text-slate-400 hover:text-white p-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 overflow-y-auto sidebar-scroll px-3 py-4 space-y-1">
        <?php foreach ($navItems as $item):
            $isActive = ($currentPage === $item['file'])
                || ($item['file'] === 'orders.php' && $currentPage === 'order-details.php')
                || ($item['file'] === 'tickets.php' && $currentPage === 'ticket-details.php');
        ?>
            <a href="/<?= e($item['file']) ?>"
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-[13.5px] font-medium whitespace-nowrap smooth-transition <?= $isActive
                   ? 'bg-[#7138D8] text-white shadow-md shadow-[#7138D8]/25'
                   : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                <svg class="w-[18px] h-[18px] shrink-0 <?= $isActive ? 'text-white' : 'text-slate-400' ?>" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="<?= e($item['icon']) ?>"/>
                </svg>
                <span class="truncate"><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Sidebar Bottom Wallet Card -->
    <?php if ($authUser): ?>
    <div class="p-3.5 border-t border-white/10 shrink-0">
        <div class="glass-sidebar-wallet rounded-2xl p-3.5">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl bg-[#16A36A]/20 text-[#16A36A] flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="text-[11px] text-slate-400 font-medium">Wallet Balance</div>
                    <div class="text-base font-bold text-[#16A36A] tabular-nums truncate"><?= e(format_money($authUser['balance'])) ?></div>
                </div>
            </div>
            <a href="/add-funds.php"
               class="mt-3 w-full inline-flex items-center justify-center gap-1.5 h-9 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-xs font-semibold smooth-transition">
                + Add Funds
            </a>
        </div>
    </div>
    <?php endif; ?>
</aside>
