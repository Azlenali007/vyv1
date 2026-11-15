<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$query      = trim((string)($_GET['q'] ?? ''));
$categoryId = (int)($_GET['category_id'] ?? 0);
$categories = Category::all(true);
$services   = Service::search($query, $categoryId, true);
$currency   = get_setting('currency_symbol', DEFAULT_CURRENCY_SYMBOL);

$pageTitle = 'Services';
include INCLUDES_PATH . '/user-header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Service Catalog</h1>
        <p class="text-sm text-slate-500 mt-0.5">Browse real-time rates per 1,000 units across active categories.</p>
    </div>
    <?php if (current_user()): ?>
        <a href="/new-order.php" class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-xs font-semibold whitespace-nowrap">
            + Place New Order
        </a>
    <?php endif; ?>
</div>

<!-- Filter Controls -->
<div class="bg-white rounded-2xl border border-slate-200/80 p-4 mb-5 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
    <div class="relative flex-1">
        <span class="absolute inset-y-0 left-3.5 flex items-center text-slate-400 pointer-events-none">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
        </span>
        <input id="liveServiceSearch" type="search" value="<?= e($query) ?>"
               placeholder="Search by service name, ID, or category..."
               class="w-full h-10 pl-10 pr-4 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]">
    </div>
    <select id="liveCategoryFilter" class="h-10 px-3.5 rounded-xl border border-slate-200 text-sm bg-white text-slate-700 focus:outline-none focus:border-[#7138D8]">
        <option value="0">All Categories</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                <?= e($cat['name']) ?> (<?= (int)$cat['service_count'] ?>)
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3.5">ID</th>
                    <th class="px-4 py-3.5">Service</th>
                    <th class="px-4 py-3.5">Rate / 1,000</th>
                    <th class="px-4 py-3.5">Min – Max</th>
                    <th class="px-4 py-3.5">Avg. Time</th>
                    <th class="px-4 py-3.5 text-right">Action</th>
                </tr>
            </thead>
            <tbody id="servicesTableBody" class="divide-y divide-slate-100">
                <?php if (empty($services)): ?>
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">
                            No active services found in the database.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($services as $s): ?>
                        <tr class="table-row-hover">
                            <td class="px-4 py-3.5 text-xs font-mono-code text-slate-500">#<?= (int)$s['id'] ?></td>
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-3">
                                    <?= platform_icon_svg((string)$s['category_platform']) ?>
                                    <div>
                                        <div class="font-medium text-sm text-[#151515]"><?= e($s['name']) ?></div>
                                        <div class="text-xs text-slate-500 mt-0.5"><?= e($s['category_name']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-sm font-semibold text-[#151515] tabular-nums"><?= e($currency . number_format((float)$s['rate_per_1000'], 2)) ?></td>
                            <td class="px-4 py-3.5 text-xs text-slate-600 tabular-nums"><?= number_format((int)$s['min_qty']) ?> – <?= number_format((int)$s['max_qty']) ?></td>
                            <td class="px-4 py-3.5 text-xs text-slate-500"><?= e($s['average_time'] ?: '—') ?></td>
                            <td class="px-4 py-3.5 text-right">
                                <a href="/new-order.php?service_id=<?= (int)$s['id'] ?>"
                                   class="inline-flex items-center px-3 py-1.5 rounded-lg bg-[#7138D8] hover:bg-[#5E28C2] text-white text-xs font-medium whitespace-nowrap">Order</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include INCLUDES_PATH . '/user-footer.php'; ?>
