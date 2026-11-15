<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('orders.manage');
$pdo   = Database::getConnection();

$status = trim((string)($_GET['status'] ?? 'all'));
$q      = trim((string)($_GET['q'] ?? ''));

$where  = [];
$params = [];
$allowed = ['pending', 'processing', 'in_progress', 'completed', 'partial', 'cancelled', 'refunded', 'failed'];
if (in_array($status, $allowed, true)) {
    $where[]  = 'o.status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(o.order_code LIKE ? OR o.link LIKE ? OR u.username LIKE ? OR s.name LIKE ?)';
    array_push($params, $like, $like, $like, $like);
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$stmt = $pdo->prepare(
    "SELECT o.*, u.username, s.name AS service_name, p.name AS provider_name
     FROM orders o
     INNER JOIN users u ON u.id = o.user_id
     INNER JOIN services s ON s.id = o.service_id
     LEFT JOIN providers p ON p.id = o.provider_id
     {$whereSql}
     ORDER BY o.id DESC LIMIT 100"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Orders';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="mb-6">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Order Management</h1>
    <p class="text-sm text-slate-500 mt-0.5">Monitor customer orders, dispatch to API providers, sync live status, and process refunds.</p>
</div>

<form method="get" class="bg-white rounded-2xl border border-slate-200/80 p-4 mb-5 flex flex-col sm:flex-row gap-3">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search order code, username, link, or service..." class="flex-1 h-10 px-3.5 rounded-xl border border-slate-200 text-sm">
    <select name="status" class="h-10 px-3.5 rounded-xl border border-slate-200 text-sm bg-white">
        <option value="all">All Statuses</option>
        <?php foreach ($allowed as $st): ?>
            <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $st))) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="h-10 px-5 rounded-xl bg-[#151515] text-white text-xs font-semibold">Filter</button>
</form>

<div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3.5">Order</th>
                    <th class="px-4 py-3.5">User</th>
                    <th class="px-4 py-3.5">Service & Link</th>
                    <th class="px-4 py-3.5 text-right">Qty</th>
                    <th class="px-4 py-3.5 text-right">Charge / Profit</th>
                    <th class="px-4 py-3.5">Status</th>
                    <th class="px-4 py-3.5 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($orders)): ?>
                    <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-slate-500">No orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): $meta = order_status_meta($o['status']); ?>
                        <tr class="table-row-hover">
                            <td class="px-4 py-3.5 font-mono-code text-xs font-semibold">
                                <a href="/admin/order-details.php?id=<?= (int)$o['id'] ?>" class="text-[#7138D8] hover:underline">#<?= e($o['order_code']) ?></a>
                                <?php if (!empty($o['provider_order_id'])): ?>
                                    <div class="text-[11px] text-slate-400">Ext #<?= e($o['provider_order_id']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3.5 text-xs font-medium text-slate-700"><?= e($o['username']) ?></td>
                            <td class="px-4 py-3.5">
                                <div class="font-medium text-xs text-[#151515] truncate max-w-[240px]"><?= e($o['service_name']) ?></div>
                                <div class="text-[11px] text-slate-400 truncate max-w-[240px]"><?= e($o['link']) ?></div>
                            </td>
                            <td class="px-4 py-3.5 text-right tabular-nums text-xs">
                                <div><?= number_format((int)$o['quantity']) ?></div>
                                <div class="text-slate-400">Rem: <?= number_format((int)$o['remains']) ?></div>
                            </td>
                            <td class="px-4 py-3.5 text-right tabular-nums text-xs">
                                <div class="font-bold text-[#151515]"><?= e(format_money($o['charge'])) ?></div>
                                <div class="text-[#16A36A] font-semibold">+<?= e(format_money($o['profit'])) ?></div>
                            </td>
                            <td class="px-4 py-3.5"><span class="px-2.5 py-0.5 rounded-lg border text-xs font-semibold <?= $meta['bg'] ?>"><?= e($meta['label']) ?></span></td>
                            <td class="px-4 py-3.5 text-right">
                                <a href="/admin/order-details.php?id=<?= (int)$o['id'] ?>" class="px-3 py-1.5 rounded-lg bg-[#7138D8]/10 text-[#7138D8] text-xs font-semibold">Manage</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
