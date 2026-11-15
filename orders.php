<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$user = require_user_login();
$pdo = Database::getConnection();

$status = trim((string)($_GET['status'] ?? 'all'));
$search = trim((string)($_GET['q'] ?? ''));

$where = ['o.user_id = ?'];
$params = [(int)$user['id']];

$allowedStatuses = ['pending', 'processing', 'in_progress', 'completed', 'partial', 'cancelled', 'refunded', 'failed'];
if (in_array($status, $allowedStatuses, true)) {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
if ($search !== '') {
    $q = '%' . $search . '%';
    $where[] = '(o.order_code LIKE ? OR o.link LIKE ? OR s.name LIKE ?)';
    $params[] = $q;
    $params[] = $q;
    $params[] = $q;
}

$sql = "SELECT o.*, s.name AS service_name, c.platform
        FROM orders o
        INNER JOIN services s ON s.id = o.service_id
        INNER JOIN categories c ON c.id = s.category_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY o.id DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Orders';
include INCLUDES_PATH . '/user-header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Order History</h1>
        <p class="text-sm text-slate-500 mt-0.5">Track fulfillment progress, remaining counts, and live provider updates.</p>
    </div>
    <a href="/new-order.php" class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-xs font-semibold whitespace-nowrap">
        + New Order
    </a>
</div>

<form method="get" class="bg-white rounded-2xl border border-slate-200/80 p-4 mb-5 flex flex-col sm:flex-row gap-3">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search by order code, link, or service..."
           class="flex-1 h-10 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]">
    <select name="status" class="h-10 px-3.5 rounded-xl border border-slate-200 text-sm bg-white focus:outline-none focus:border-[#7138D8]">
        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
        <?php foreach ($allowedStatuses as $st): ?>
            <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $st))) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="h-10 px-5 rounded-xl bg-[#151515] text-white text-xs font-semibold whitespace-nowrap">Filter</button>
</form>

<div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3.5">Order ID</th>
                    <th class="px-4 py-3.5">Service & Link</th>
                    <th class="px-4 py-3.5 text-right">Quantity</th>
                    <th class="px-4 py-3.5 text-right">Charge</th>
                    <th class="px-4 py-3.5">Status</th>
                    <th class="px-4 py-3.5 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">
                            No orders found matching your criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $ord):
                        $meta = order_status_meta($ord['status']);
                    ?>
                        <tr class="table-row-hover">
                            <td class="px-4 py-3.5 font-mono-code text-xs font-semibold">
                                <a href="/order-details.php?id=<?= (int)$ord['id'] ?>" class="text-[#7138D8] hover:underline">#<?= e($ord['order_code']) ?></a>
                                <div class="text-[11px] font-sans font-normal text-slate-400 mt-0.5"><?= e(date('d M, H:i', strtotime($ord['created_at']))) ?></div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <?= platform_icon_svg((string)$ord['platform']) ?>
                                    <div class="min-w-0">
                                        <div class="font-medium text-[#151515] truncate max-w-[260px]"><?= e($ord['service_name']) ?></div>
                                        <div class="text-xs text-slate-400 truncate max-w-[260px]"><?= e($ord['link']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-right tabular-nums">
                                <div class="font-medium text-[#151515]"><?= number_format((int)$ord['quantity']) ?></div>
                                <div class="text-[11px] text-slate-400">Remains: <?= number_format((int)$ord['remains']) ?></div>
                            </td>
                            <td class="px-4 py-3.5 text-right font-semibold text-[#151515] tabular-nums"><?= e(format_money($ord['charge'])) ?></td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex px-2.5 py-0.5 rounded-lg border text-xs font-semibold <?= $meta['bg'] ?>"><?= e($meta['label']) ?></span>
                            </td>
                            <td class="px-4 py-3.5 text-right space-x-2 whitespace-nowrap">
                                <?php if (!empty($ord['provider_order_id']) && !in_array($ord['status'], ['completed', 'cancelled', 'refunded'], true)): ?>
                                    <button type="button" onclick="window.SMMOrders.checkStatus(<?= (int)$ord['id'] ?>, this)"
                                            class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                        Sync
                                    </button>
                                <?php endif; ?>
                                <a href="/order-details.php?id=<?= (int)$ord['id'] ?>" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-xs font-medium text-slate-700">Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include INCLUDES_PATH . '/user-footer.php'; ?>
