<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('dashboard.view');
$pdo   = Database::getConnection();

// Real SQL Analytics (Zero fake numbers)
$totalUsers   = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$orderSummary = $pdo->query(
    "SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(CASE WHEN status NOT IN ('cancelled', 'refunded', 'failed') THEN charge - refunded_amount ELSE 0 END), 0) AS total_revenue,
        COALESCE(SUM(CASE WHEN status NOT IN ('cancelled', 'refunded', 'failed') THEN profit ELSE 0 END), 0) AS total_profit,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN status IN ('processing', 'in_progress') THEN 1 ELSE 0 END) AS processing_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status IN ('cancelled', 'refunded', 'failed') THEN 1 ELSE 0 END) AS cancelled_count
     FROM orders"
)->fetch() ?: [];

$totalOrders     = (int)($orderSummary['total_orders'] ?? 0);
$totalRevenue    = (float)($orderSummary['total_revenue'] ?? 0);
$totalProfit     = (float)($orderSummary['total_profit'] ?? 0);
$completedCount  = (int)($orderSummary['completed_count'] ?? 0);
$processingCount = (int)($orderSummary['processing_count'] ?? 0);
$pendingCount    = (int)($orderSummary['pending_count'] ?? 0);
$cancelledCount  = (int)($orderSummary['cancelled_count'] ?? 0);

$pendingPayments = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$openTickets     = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status != 'closed'")->fetchColumn();

// 7-day / 30-day revenue series
$days = (($_GET['range'] ?? '7') === '30') ? 30 : 7;
$dailyMap = [];
$seriesStmt = $pdo->prepare(
    "SELECT DATE(created_at) AS day_key,
            COALESCE(SUM(CASE WHEN status NOT IN ('cancelled', 'refunded', 'failed') THEN charge - refunded_amount ELSE 0 END), 0) AS rev
     FROM orders
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     GROUP BY DATE(created_at)"
);
$seriesStmt->execute([$days - 1]);
foreach ($seriesStmt->fetchAll() as $r) {
    $dailyMap[$r['day_key']] = (float)$r['rev'];
}
$chartLabels = [];
$chartValues = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = $days === 7 ? date('D', strtotime($d)) : date('d M', strtotime($d));
    $chartValues[] = round($dailyMap[$d] ?? 0.0, 2);
}

$recentOrders = $pdo->query(
    "SELECT o.*, u.username, s.name AS service_name, c.platform
     FROM orders o
     INNER JOIN users u ON u.id = o.user_id
     INNER JOIN services s ON s.id = o.service_id
     INNER JOIN categories c ON c.id = s.category_id
     ORDER BY o.id DESC LIMIT 6"
)->fetchAll();

$announcements = $pdo->query("SELECT * FROM announcements ORDER BY id DESC LIMIT 4")->fetchAll();

$pageTitle = 'Dashboard';
include INCLUDES_PATH . '/admin-header.php';
?>

<!-- 4 Primary KPI Cards (Strictly Real MySQL Queries) -->
<section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="glass-kpi glass-kpi-purple rounded-2xl p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-2xl bg-[#7138D8] text-white flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </span>
        <div>
            <div class="text-xs font-medium text-slate-500">Total Users</div>
            <div class="text-2xl font-bold text-[#151515] tabular-nums mt-0.5"><?= number_format($totalUsers) ?></div>
        </div>
    </div>

    <div class="glass-kpi glass-kpi-green rounded-2xl p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-2xl bg-[#16A36A] text-white flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
        </span>
        <div>
            <div class="text-xs font-medium text-slate-500">Total Orders</div>
            <div class="text-2xl font-bold text-[#151515] tabular-nums mt-0.5"><?= number_format($totalOrders) ?></div>
        </div>
    </div>

    <div class="glass-kpi glass-kpi-green rounded-2xl p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
        </span>
        <div>
            <div class="text-xs font-medium text-slate-500">Total Revenue</div>
            <div class="text-2xl font-bold text-[#151515] tabular-nums mt-0.5"><?= e(format_money($totalRevenue)) ?></div>
        </div>
    </div>

    <div class="glass-kpi rounded-2xl p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-2xl bg-[#151515] text-white flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
        </span>
        <div>
            <div class="text-xs font-medium text-slate-500">Total Profit</div>
            <div class="text-2xl font-bold text-[#16A36A] tabular-nums mt-0.5"><?= e(format_money($totalProfit)) ?></div>
        </div>
    </div>
</section>

<!-- Quick Action Bar -->
<section class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <a href="/admin/payments.php?status=pending" class="bg-white rounded-2xl border border-slate-200/80 p-4 flex items-center justify-between hover:border-[#7138D8] smooth-transition">
        <div>
            <div class="text-xs font-medium text-slate-500">Pending Deposit Approvals</div>
            <div class="text-lg font-bold text-[#151515] tabular-nums mt-0.5"><?= number_format($pendingPayments) ?></div>
        </div>
        <span class="text-xs font-semibold text-[#7138D8]">Review Payments &rarr;</span>
    </a>
    <a href="/admin/tickets.php?status=open" class="bg-white rounded-2xl border border-slate-200/80 p-4 flex items-center justify-between hover:border-[#7138D8] smooth-transition">
        <div>
            <div class="text-xs font-medium text-slate-500">Open Support Tickets</div>
            <div class="text-lg font-bold text-[#151515] tabular-nums mt-0.5"><?= number_format($openTickets) ?></div>
        </div>
        <span class="text-xs font-semibold text-[#7138D8]">Reply to Tickets &rarr;</span>
    </a>
</section>

<!-- Charts Row -->
<section class="grid grid-cols-1 xl:grid-cols-12 gap-5 mb-6">
    <div class="xl:col-span-8 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h2 class="text-base font-bold text-[#151515]">Sales Overview</h2>
            <form method="get">
                <select name="range" onchange="this.form.submit()" class="h-9 px-3 rounded-xl border border-slate-200 text-xs font-medium bg-white">
                    <option value="7" <?= $days === 7 ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="30" <?= $days === 30 ? 'selected' : '' ?>>Last 30 Days</option>
                </select>
            </form>
        </div>
        <div class="h-64"><canvas id="salesOverviewChart"></canvas></div>
    </div>

    <div class="xl:col-span-4 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 flex flex-col justify-between">
        <h2 class="text-base font-bold text-[#151515] mb-4">Order Status</h2>
        <?php if ($totalOrders === 0): ?>
            <div class="flex-1 flex flex-col items-center justify-center py-8 text-center">
                <div class="text-sm font-semibold text-slate-700">No Orders Recorded</div>
                <p class="text-xs text-slate-500 mt-1">Order status breakdown will display once orders are created.</p>
            </div>
        <?php else: ?>
            <div class="flex items-center gap-5 my-auto">
                <div class="relative w-36 h-36 shrink-0">
                    <canvas id="orderStatusChart"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-xl font-bold text-[#151515] tabular-nums"><?= number_format($totalOrders) ?></span>
                        <span class="text-[11px] text-slate-500">Orders</span>
                    </div>
                </div>
                <div class="flex-1 space-y-2.5 text-xs">
                    <?php
                    $pct = fn(int $n) => $totalOrders > 0 ? round(($n / $totalOrders) * 100, 1) : 0;
                    foreach ([
                        ['Completed',  $completedCount,  '#16A36A'],
                        ['Processing', $processingCount, '#7138D8'],
                        ['Pending',    $pendingCount,    '#F59E0B'],
                        ['Cancelled',  $cancelledCount,  '#DC2626'],
                    ] as [$lbl, $cnt, $clr]): ?>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-slate-600"><span class="w-2.5 h-2.5 rounded-full" style="background: <?= $clr ?>"></span><?= e($lbl) ?></span>
                            <span class="font-semibold text-[#151515] tabular-nums"><?= $pct($cnt) ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="grid grid-cols-1 xl:grid-cols-12 gap-5">
    <div class="xl:col-span-7 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-bold text-[#151515]">Recent Orders</h2>
            <a href="/admin/orders.php" class="text-xs font-semibold text-[#7138D8] hover:underline">View All &rarr;</a>
        </div>
        <?php if (empty($recentOrders)): ?>
            <div class="py-10 text-center text-sm text-slate-500">No orders in database yet.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500">
                            <th class="py-2.5 pr-3">Order</th>
                            <th class="py-2.5 px-3">User</th>
                            <th class="py-2.5 px-3">Service</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 pl-3 text-right">Charge</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php foreach ($recentOrders as $o): $meta = order_status_meta($o['status']); ?>
                            <tr class="table-row-hover">
                                <td class="py-3 pr-3 font-mono-code text-xs font-semibold"><a href="/admin/order-details.php?id=<?= (int)$o['id'] ?>" class="text-[#7138D8] hover:underline">#<?= e($o['order_code']) ?></a></td>
                                <td class="py-3 px-3 text-xs text-slate-700"><?= e($o['username']) ?></td>
                                <td class="py-3 px-3 text-xs text-slate-900 truncate max-w-[180px]"><?= e($o['service_name']) ?></td>
                                <td class="py-3 px-3"><span class="px-2 py-0.5 rounded-lg border text-[11px] font-semibold <?= $meta['bg'] ?>"><?= e($meta['label']) ?></span></td>
                                <td class="py-3 pl-3 text-right font-semibold tabular-nums"><?= e(format_money($o['charge'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="xl:col-span-5 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-bold text-[#151515]">Latest Announcements</h2>
            <a href="/admin/announcements.php" class="text-xs font-semibold text-[#7138D8] hover:underline">Manage &rarr;</a>
        </div>
        <?php if (empty($announcements)): ?>
            <div class="py-10 text-center text-sm text-slate-500">No announcements created yet.</div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($announcements as $ann): ?>
                    <div class="py-3 first:pt-0 last:pb-0">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-[#151515]"><?= e($ann['title']) ?></span>
                            <span class="text-[11px] text-slate-400"><?= e(date('d M', strtotime($ann['created_at']))) ?></span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1"><?= e($ann['content']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
window.DASHBOARD_CHART_DATA = <?= json_encode([
    'label'  => 'Revenue (' . get_setting('currency_symbol', DEFAULT_CURRENCY_SYMBOL) . ')',
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

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
