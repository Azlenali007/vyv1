<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$user = require_user_login();
$userId = (int)$user['id'];
$pdo = Database::getConnection();

// Real SQL KPI queries for this user
$orderCountsStmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN status IN ('processing', 'in_progress') THEN 1 ELSE 0 END) AS processing_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status IN ('cancelled', 'refunded', 'failed') THEN 1 ELSE 0 END) AS cancelled_count
     FROM orders WHERE user_id = ?"
);
$orderCountsStmt->execute([$userId]);
$counts = $orderCountsStmt->fetch() ?: [];

$totalOrders     = (int)($counts['total_orders'] ?? 0);
$completedCount  = (int)($counts['completed_count'] ?? 0);
$processingCount = (int)($counts['processing_count'] ?? 0);
$pendingCount    = (int)($counts['pending_count'] ?? 0);
$cancelledCount  = (int)($counts['cancelled_count'] ?? 0);
$activeOrders    = $pendingCount + $processingCount;

// 7-day / 30-day real spending chart
$days = (($_GET['range'] ?? '7') === '30') ? 30 : 7;
$chartLabels = [];
$chartValues = [];
$dailyMap = [];

$seriesStmt = $pdo->prepare(
    "SELECT DATE(created_at) AS day_key, COALESCE(SUM(charge), 0) AS total_charge
     FROM orders
     WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     GROUP BY DATE(created_at)"
);
$seriesStmt->execute([$userId, $days - 1]);
foreach ($seriesStmt->fetchAll() as $row) {
    $dailyMap[$row['day_key']] = (float)$row['total_charge'];
}
for ($i = $days - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = $days === 7 ? date('D', strtotime($d)) : date('d M', strtotime($d));
    $chartValues[] = round($dailyMap[$d] ?? 0.0, 2);
}

// Recent orders from DB
$recentStmt = $pdo->prepare(
    "SELECT o.*, s.name AS service_name, c.platform
     FROM orders o
     INNER JOIN services s ON s.id = o.service_id
     INNER JOIN categories c ON c.id = s.category_id
     WHERE o.user_id = ?
     ORDER BY o.id DESC LIMIT 6"
);
$recentStmt->execute([$userId]);
$recentOrders = $recentStmt->fetchAll();

// Published announcements from DB
$announcements = $pdo->query(
    "SELECT * FROM announcements WHERE is_published = 1 ORDER BY id DESC LIMIT 4"
)->fetchAll();

$pageTitle = 'Dashboard';
include INCLUDES_PATH . '/user-header.php';
?>

<!-- Welcome Hero Banner (Matches Design Target) -->
<section class="hero-banner-gradient rounded-3xl p-6 sm:p-8 text-white shadow-lg shadow-[#7138D8]/15 mb-6 flex flex-col md:flex-row md:items-center justify-between gap-6">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">Welcome Back, <?= e($user['full_name']) ?>!</h1>
        <p class="text-sm sm:text-base text-purple-100/90 mt-1.5">Let's grow your social media presence with automated fulfillment.</p>
        <div class="mt-5 flex flex-wrap items-center gap-3">
            <a href="/new-order.php" class="px-4 py-2.5 rounded-xl bg-white text-[#151515] hover:bg-purple-50 text-xs font-bold smooth-transition">+ Place New Order</a>
            <a href="/services.php" class="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/15 text-xs font-semibold smooth-transition">Browse Services</a>
        </div>
    </div>

    <div class="hidden md:flex items-center gap-3 shrink-0 opacity-95">
        <div class="w-12 h-12 rounded-2xl bg-white/10 border border-white/15 flex items-center justify-center">
            <svg class="w-6 h-6 text-pink-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/></svg>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-white/10 border border-white/15 flex items-center justify-center">
            <svg class="w-6 h-6 text-red-400" fill="currentColor" viewBox="0 0 24 24"><path d="M21.6 7.2a2.7 2.7 0 0 0-1.9-1.9C18 4.8 12 4.8 12 4.8s-6 0-7.7.5a2.7 2.7 0 0 0-1.9 1.9C2 8.9 2 12 2 12s0 3.1.4 4.8a2.7 2.7 0 0 0 1.9 1.9c1.7.5 7.7.5 7.7.5s6 0 7.7-.5a2.7 2.7 0 0 0 1.9-1.9c.4-1.7.4-4.8.4-4.8s0-3.1-.4-4.8zM10 15V9l5.2 3-5.2 3z"/></svg>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-white/10 border border-white/15 flex items-center justify-center">
            <svg class="w-6 h-6 text-sky-300" fill="currentColor" viewBox="0 0 24 24"><path d="M21.5 3.8 2.6 11.1c-1.3.5-1.3 1.3-.2 1.6l4.8 1.5 11.2-7.1c.5-.3 1-.1.6.2l-9.1 8.2-.3 4.8c.5 0 .7-.2 1-.5l2.3-2.2 4.8 3.5c.9.5 1.5.2 1.7-.8l3.1-14.6c.3-1.3-.5-1.9-1-1.9z"/></svg>
        </div>
    </div>
</section>

<!-- 4 Glass KPI Cards (100% Real DB Figures) -->
<section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="glass-kpi glass-kpi-green rounded-2xl p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-2xl bg-[#16A36A] text-white flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        </span>
        <div class="min-w-0">
            <div class="text-xs font-medium text-slate-500">Wallet Balance</div>
            <div class="text-2xl font-bold text-[#151515] tabular-nums mt-0.5 truncate"><?= e(format_money($user['balance'])) ?></div>
        </div>
    </div>

    <div class="glass-kpi glass-kpi-purple rounded-2xl p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-2xl bg-[#7138D8] text-white flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
        </span>
        <div class="min-w-0">
            <div class="text-xs font-medium text-slate-500">Total Orders</div>
            <div class="text-2xl font-bold text-[#151515] tabular-nums mt-0.5"><?= number_format($totalOrders) ?></div>
        </div>
    </div>

    <div class="glass-kpi rounded-2xl p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-2xl bg-[#151515] text-white flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
        </span>
        <div class="min-w-0">
            <div class="text-xs font-medium text-slate-500">Total Spent</div>
            <div class="text-2xl font-bold text-[#151515] tabular-nums mt-0.5 truncate"><?= e(format_money($user['total_spent'])) ?></div>
        </div>
    </div>

    <div class="glass-kpi glass-kpi-green rounded-2xl p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-2xl bg-emerald-600/15 text-[#16A36A] flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </span>
        <div class="min-w-0">
            <div class="text-xs font-medium text-slate-500">Active Orders</div>
            <div class="text-2xl font-bold text-[#151515] tabular-nums mt-0.5"><?= number_format($activeOrders) ?></div>
        </div>
    </div>
</section>

<!-- Charts Row -->
<section class="grid grid-cols-1 xl:grid-cols-12 gap-5 mb-6">
    <!-- Spending Overview Line Chart -->
    <div class="xl:col-span-8 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h2 class="text-base font-bold text-[#151515]">Spending Overview</h2>
            <form method="get" class="shrink-0">
                <select name="range" onchange="this.form.submit()"
                        class="h-9 px-3 rounded-xl border border-slate-200 bg-white text-xs font-medium text-slate-700 focus:outline-none focus:border-[#7138D8]">
                    <option value="7" <?= $days === 7 ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="30" <?= $days === 30 ? 'selected' : '' ?>>Last 30 Days</option>
                </select>
            </form>
        </div>
        <div class="h-64">
            <canvas id="salesOverviewChart"></canvas>
        </div>
    </div>

    <!-- Order Status Doughnut -->
    <div class="xl:col-span-4 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 flex flex-col justify-between">
        <h2 class="text-base font-bold text-[#151515] mb-4">Order Status</h2>

        <?php if ($totalOrders === 0): ?>
            <div class="flex-1 flex flex-col items-center justify-center py-8 text-center">
                <div class="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
                <div class="text-sm font-semibold text-slate-800">No Orders Yet</div>
                <p class="text-xs text-slate-500 mt-1 max-w-[210px]">Status distribution appears automatically once you place your first order.</p>
            </div>
        <?php else: ?>
            <div class="flex items-center gap-5 my-auto">
                <div class="relative w-36 h-36 shrink-0">
                    <canvas id="orderStatusChart"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-xl font-bold text-[#151515] tabular-nums"><?= number_format($totalOrders) ?></span>
                        <span class="text-[11px] text-slate-500">Total</span>
                    </div>
                </div>
                <div class="flex-1 space-y-2.5 text-xs">
                    <?php
                    $pct = fn(int $n) => $totalOrders > 0 ? round(($n / $totalOrders) * 100, 1) : 0;
                    $rows = [
                        ['Completed',  $completedCount,  '#16A36A'],
                        ['Processing', $processingCount, '#7138D8'],
                        ['Pending',    $pendingCount,    '#F59E0B'],
                        ['Cancelled',  $cancelledCount,  '#DC2626'],
                    ];
                    foreach ($rows as [$label, $cnt, $color]): ?>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-slate-600">
                                <span class="w-2.5 h-2.5 rounded-full" style="background: <?= $color ?>"></span>
                                <?= e($label) ?>
                            </span>
                            <span class="font-semibold text-[#151515] tabular-nums"><?= $pct($cnt) ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Bottom Row: Recent Orders & Announcements -->
<section class="grid grid-cols-1 xl:grid-cols-12 gap-5">
    <div class="xl:col-span-7 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h2 class="text-base font-bold text-[#151515]">Recent Orders</h2>
            <a href="/orders.php" class="text-xs font-semibold text-[#7138D8] hover:underline whitespace-nowrap">View All &rarr;</a>
        </div>

        <?php if (empty($recentOrders)): ?>
            <div class="py-10 text-center">
                <p class="text-sm text-slate-500">You have not placed any orders yet.</p>
                <a href="/new-order.php" class="mt-3 inline-flex items-center px-4 py-2 rounded-xl bg-[#7138D8] text-white text-xs font-semibold">Place First Order</a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500">
                            <th class="py-2.5 pr-3">Order ID</th>
                            <th class="py-2.5 px-3">Service</th>
                            <th class="py-2.5 px-3 text-right">Qty</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 pl-3 text-right">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php foreach ($recentOrders as $ord):
                            $meta = order_status_meta($ord['status']);
                        ?>
                            <tr class="table-row-hover">
                                <td class="py-3 pr-3 font-mono-code text-xs font-semibold">
                                    <a href="/order-details.php?id=<?= (int)$ord['id'] ?>" class="text-[#7138D8] hover:underline">#<?= e($ord['order_code']) ?></a>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <?= platform_icon_svg((string)$ord['platform']) ?>
                                        <span class="truncate max-w-[200px] font-medium text-[#151515]"><?= e($ord['service_name']) ?></span>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-right tabular-nums text-slate-700"><?= number_format((int)$ord['quantity']) ?></td>
                                <td class="py-3 px-3">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-lg border text-xs font-semibold <?= $meta['bg'] ?>"><?= e($meta['label']) ?></span>
                                </td>
                                <td class="py-3 pl-3 text-right text-xs text-slate-500 whitespace-nowrap"><?= e(time_ago($ord['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="xl:col-span-5 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6" id="announcements">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h2 class="text-base font-bold text-[#151515]">Latest Announcements</h2>
        </div>

        <?php if (empty($announcements)): ?>
            <div class="py-10 text-center text-sm text-slate-500">
                No announcements published yet.
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($announcements as $ann): ?>
                    <div class="py-3.5 first:pt-0 last:pb-0">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm font-semibold text-[#151515]"><?= e($ann['title']) ?></div>
                            <span class="text-[11px] text-slate-400 whitespace-nowrap"><?= e(date('d M', strtotime($ann['created_at']))) ?></span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed"><?= e($ann['content']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
window.DASHBOARD_CHART_DATA = <?= json_encode([
    'label'  => 'Spending (' . get_setting('currency_symbol', DEFAULT_CURRENCY_SYMBOL) . ')',
    'labels' => $chartLabels,
    'values' => $chartValues,
]) ?>;
window.ORDER_STATUS_DATA = <?= json_encode([
    'completed'  => $completedCount,
    'processing' => $processingCount,
    'pending'    => $pendingCount,
    'cancelled'  => $cancelledCount,
]) ?>;
</script>

<?php include INCLUDES_PATH . '/user-footer.php'; ?>
