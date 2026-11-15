<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('reports.view');
$pdo   = Database::getConnection();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS orders_count,
        COALESCE(SUM(charge), 0) AS gross_charge,
        COALESCE(SUM(refunded_amount), 0) AS total_refunded,
        COALESCE(SUM(CASE WHEN status NOT IN ('cancelled', 'refunded', 'failed') THEN provider_cost ELSE 0 END), 0) AS total_cost,
        COALESCE(SUM(CASE WHEN status NOT IN ('cancelled', 'refunded', 'failed') THEN profit ELSE 0 END), 0) AS net_profit
     FROM orders
     WHERE DATE(created_at) BETWEEN ? AND ?"
);
$stmt->execute([$from, $to]);
$summary = $stmt->fetch() ?: [];

$topServicesStmt = $pdo->prepare(
    "SELECT s.id, s.name, COUNT(o.id) AS order_count, COALESCE(SUM(o.charge - o.refunded_amount), 0) AS revenue, COALESCE(SUM(o.profit), 0) AS profit
     FROM orders o
     INNER JOIN services s ON s.id = o.service_id
     WHERE DATE(o.created_at) BETWEEN ? AND ?
     GROUP BY s.id, s.name
     ORDER BY revenue DESC LIMIT 10"
);
$topServicesStmt->execute([$from, $to]);
$topServices = $topServicesStmt->fetchAll();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="smm-report-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Service ID', 'Service Name', 'Orders', 'Revenue', 'Profit']);
    foreach ($topServices as $row) {
        fputcsv($out, [$row['id'], $row['name'], $row['order_count'], $row['revenue'], $row['profit']]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Reports';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Financial & Order Reports</h1>
        <p class="text-sm text-slate-500 mt-0.5">Live date-range breakdown directly from MySQL order and wallet records.</p>
    </div>
    <a href="?from=<?= e($from) ?>&to=<?= e($to) ?>&export=csv" class="px-4 py-2.5 rounded-xl bg-[#151515] text-white text-xs font-semibold whitespace-nowrap">Export CSV</a>
</div>

<form method="get" class="bg-white rounded-2xl border border-slate-200/80 p-4 mb-6 flex flex-wrap items-end gap-3">
    <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1">From Date</label>
        <input type="date" name="from" value="<?= e($from) ?>" class="h-10 px-3 rounded-xl border border-slate-200 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1">To Date</label>
        <input type="date" name="to" value="<?= e($to) ?>" class="h-10 px-3 rounded-xl border border-slate-200 text-sm">
    </div>
    <button type="submit" class="h-10 px-5 rounded-xl bg-[#7138D8] text-white text-xs font-semibold">Run Report</button>
</form>

<section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5">
        <div class="text-xs text-slate-500">Orders in Range</div>
        <div class="text-2xl font-bold text-[#151515] tabular-nums mt-1"><?= number_format((int)($summary['orders_count'] ?? 0)) ?></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5">
        <div class="text-xs text-slate-500">Gross Order Volume</div>
        <div class="text-2xl font-bold text-[#151515] tabular-nums mt-1"><?= e(format_money($summary['gross_charge'] ?? 0)) ?></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5">
        <div class="text-xs text-slate-500">Refunded Amount</div>
        <div class="text-2xl font-bold text-rose-600 tabular-nums mt-1"><?= e(format_money($summary['total_refunded'] ?? 0)) ?></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5">
        <div class="text-xs text-slate-500">Net Profit</div>
        <div class="text-2xl font-bold text-[#16A36A] tabular-nums mt-1"><?= e(format_money($summary['net_profit'] ?? 0)) ?></div>
    </div>
</section>

<div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100">
        <h2 class="text-base font-bold text-[#151515]">Top Services by Revenue</h2>
    </div>
    <table class="w-full text-left border-collapse text-sm">
        <thead>
            <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold text-slate-500">
                <th class="px-4 py-3">Service</th>
                <th class="px-4 py-3 text-right">Orders</th>
                <th class="px-4 py-3 text-right">Revenue</th>
                <th class="px-4 py-3 text-right">Net Profit</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($topServices)): ?>
                <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">No order data in the selected date range.</td></tr>
            <?php else: ?>
                <?php foreach ($topServices as $ts): ?>
                    <tr>
                        <td class="px-4 py-3 font-medium text-[#151515]"><?= e($ts['name']) ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= number_format((int)$ts['order_count']) ?></td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold"><?= e(format_money($ts['revenue'])) ?></td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold text-[#16A36A]"><?= e(format_money($ts['profit'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
