<?php
declare(strict_types=1);

$siteName    = get_setting('site_name', APP_NAME_DEFAULT);
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');

$adminNav = [
    ['file' => 'dashboard.php',       'label' => 'Dashboard',       'group' => 'Overview'],
    ['file' => 'orders.php',          'label' => 'Orders',          'group' => 'Operations'],
    ['file' => 'services.php',        'label' => 'Services',        'group' => 'Operations'],
    ['file' => 'categories.php',      'label' => 'Categories',      'group' => 'Operations'],
    ['file' => 'providers.php',       'label' => 'API Providers',   'group' => 'Operations'],
    ['file' => 'import-services.php', 'label' => 'Import Services', 'group' => 'Operations'],
    ['file' => 'users.php',           'label' => 'Users',           'group' => 'Customers'],
    ['file' => 'payments.php',        'label' => 'Payments',        'group' => 'Finance'],
    ['file' => 'transactions.php',    'label' => 'Transactions',    'group' => 'Finance'],
    ['file' => 'tickets.php',         'label' => 'Support Tickets', 'group' => 'Support'],
    ['file' => 'announcements.php',   'label' => 'Announcements',   'group' => 'Support'],
    ['file' => 'reports.php',         'label' => 'Reports',         'group' => 'Analytics'],
    ['file' => 'settings.php',        'label' => 'Settings',        'group' => 'System'],
    ['file' => 'admins.php',          'label' => 'Admins & Roles',  'group' => 'System'],
    ['file' => 'activity-logs.php',   'label' => 'Activity Logs',   'group' => 'System'],
    ['file' => 'backup.php',          'label' => 'Database Backup', 'group' => 'System'],
];
?>
<div x-show="mobileMenuOpen" x-cloak @click="mobileMenuOpen = false"
     class="fixed inset-0 bg-black/50 z-40 lg:hidden"></div>

<aside :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       class="fixed inset-y-0 left-0 z-50 w-[260px] bg-[#151515] text-white flex flex-col smooth-transition border-r border-white/5">
    <div class="h-16 px-5 flex items-center justify-between border-b border-white/10 shrink-0">
        <a href="/admin/dashboard.php" class="flex items-center gap-3 min-w-0">
            <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#7138D8] to-[#9333EA] flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm0 2h14v2H5v-2z"/>
                </svg>
            </span>
            <div class="min-w-0">
                <div class="text-sm font-bold text-white truncate"><?= e($siteName) ?></div>
                <div class="text-[10px] text-slate-400">Admin Console</div>
            </div>
        </a>
    </div>

    <nav class="flex-1 overflow-y-auto sidebar-scroll px-3 py-4 space-y-0.5">
        <?php
        $lastGroup = '';
        foreach ($adminNav as $item):
            if ($item['group'] !== $lastGroup):
                $lastGroup = $item['group'];
        ?>
            <div class="px-3 pt-3 pb-1 text-[11px] font-semibold text-slate-500"><?= e($lastGroup) ?></div>
        <?php endif;
            $isActive = ($currentPage === $item['file'])
                || ($item['file'] === 'users.php' && in_array($currentPage, ['user-add.php', 'user-edit.php', 'user-details.php'], true))
                || ($item['file'] === 'services.php' && in_array($currentPage, ['service-add.php', 'service-edit.php'], true))
                || ($item['file'] === 'providers.php' && in_array($currentPage, ['provider-add.php', 'provider-edit.php'], true))
                || ($item['file'] === 'orders.php' && $currentPage === 'order-details.php');
        ?>
            <a href="/admin/<?= e($item['file']) ?>"
               class="flex items-center px-3.5 py-2 rounded-xl text-[13px] font-medium whitespace-nowrap smooth-transition <?= $isActive
                   ? 'bg-[#7138D8] text-white shadow-md shadow-[#7138D8]/25'
                   : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                <?= e($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
