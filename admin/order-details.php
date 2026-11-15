<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin   = require_admin_permission('orders.manage');
$pdo     = Database::getConnection();
$orderId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT o.*, u.username, u.email, s.name AS service_name, p.name AS provider_name
     FROM orders o
     INNER JOIN users u ON u.id = o.user_id
     INNER JOIN services s ON s.id = o.service_id
     LEFT JOIN providers p ON p.id = o.provider_id
     WHERE o.id = ? LIMIT 1"
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('/admin/orders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $newStatus  = (string)($_POST['status'] ?? $order['status']);
        $startCount = max(0, (int)($_POST['start_count'] ?? 0));
        $remains    = max(0, (int)($_POST['remains'] ?? 0));
        $allowed    = ['pending', 'processing', 'in_progress', 'completed', 'partial', 'cancelled', 'failed'];

        if (in_array($newStatus, $allowed, true)) {
            $pdo->prepare('UPDATE orders SET status = ?, start_count = ?, remains = ? WHERE id = ?')
                ->execute([$newStatus, $startCount, $remains, $orderId]);
            Order::addLog($orderId, $order['status'], $newStatus, "Admin updated status to {$newStatus}", 'admin', (int)$admin['id']);

            if (in_array($newStatus, ['cancelled', 'failed', 'partial'], true) && (int)$order['is_refunded'] === 0) {
                $ref = Order::processRefund($orderId, (int)$admin['id'], 'admin');
                set_flash($ref['ok'] ? 'success' : 'error', $ref['ok'] ? 'Order updated & refunded ' . format_money($ref['refunded_amount']) : $ref['error']);
            } else {
                set_flash('success', 'Order status updated.');
            }
        }
    } elseif ($action === 'refund') {
        $ref = Order::processRefund($orderId, (int)$admin['id'], 'admin');
        set_flash($ref['ok'] ? 'success' : 'error', $ref['ok'] ? 'Refunded ' . format_money($ref['refunded_amount']) : $ref['error']);
    }

    redirect('/admin/order-details.php?id=' . $orderId);
}

$logsStmt = $pdo->prepare('SELECT * FROM order_logs WHERE order_id = ? ORDER BY id DESC');
$logsStmt->execute([$orderId]);
$logs = $logsStmt->fetchAll();

$meta = order_status_meta($order['status']);
$pageTitle = 'Order #' . $order['order_code'];
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <a href="/admin/orders.php" class="text-xs font-semibold text-[#7138D8] hover:underline">&larr; Back to Orders</a>
        <h1 class="text-xl font-bold text-[#151515] mt-1 font-mono-code">Order #<?= e($order['order_code']) ?></h1>
    </div>

    <div class="flex flex-wrap gap-2"
         x-data="{
            async dispatchProvider() {
                const res = await window.SMM.apiPost('/api/provider-order.php', { order_id: <?= $orderId ?> });
                if (res.ok) window.location.reload();
                else alert(res.error || 'Dispatch failed');
            },
            async syncStatus() {
                const res = await window.SMM.apiPost('/api/provider-status.php', { order_id: <?= $orderId ?> });
                if (res.ok) window.location.reload();
                else alert(res.error || 'Sync failed');
            }
         }">
        <?php if (empty($order['provider_order_id'])): ?>
            <button type="button" @click="dispatchProvider()" class="h-10 px-4 rounded-xl bg-[#7138D8] text-white text-xs font-semibold">Dispatch to Provider</button>
        <?php else: ?>
            <button type="button" @click="syncStatus()" class="h-10 px-4 rounded-xl bg-[#7138D8] text-white text-xs font-semibold">Sync Provider Status</button>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-7 space-y-5">
        <div class="bg-white rounded-2xl border border-slate-200/80 p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div>
                    <div class="font-bold text-[#151515]"><?= e($order['service_name']) ?></div>
                    <div class="text-xs text-slate-500">Customer: <a href="/admin/user-details.php?id=<?= (int)$order['user_id'] ?>" class="text-[#7138D8] font-semibold hover:underline"><?= e($order['username']) ?></a></div>
                </div>
                <span class="px-3 py-1 rounded-lg border text-xs font-semibold <?= $meta['bg'] ?>"><?= e($meta['label']) ?></span>
            </div>

            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4 text-sm">
                <div><dt class="text-xs text-slate-500">Quantity</dt><dd class="font-bold tabular-nums"><?= number_format((int)$order['quantity']) ?></dd></div>
                <div><dt class="text-xs text-slate-500">Charge</dt><dd class="font-bold tabular-nums"><?= e(format_money($order['charge'])) ?></dd></div>
                <div><dt class="text-xs text-slate-500">Provider Cost</dt><dd class="font-bold tabular-nums"><?= e(format_money($order['provider_cost'])) ?></dd></div>
                <div><dt class="text-xs text-slate-500">Net Profit</dt><dd class="font-bold text-[#16A36A] tabular-nums"><?= e(format_money($order['profit'])) ?></dd></div>
                <div class="col-span-2 sm:col-span-4">
                    <dt class="text-xs text-slate-500">Target Link</dt>
                    <dd class="font-mono-code text-xs text-[#7138D8] break-all mt-0.5"><?= e($order['link']) ?></dd>
                </div>
            </dl>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/80 p-6">
            <h2 class="text-sm font-bold text-[#151515] mb-4">Manual Status & Refund Control</h2>
            <form method="post" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_status">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Status</label>
                        <select name="status" class="w-full h-10 px-3 rounded-xl border border-slate-200 text-xs bg-white">
                            <?php foreach (['pending', 'processing', 'in_progress', 'completed', 'partial', 'cancelled', 'failed'] as $st): ?>
                                <option value="<?= e($st) ?>" <?= $order['status'] === $st ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $st))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Start Count</label>
                        <input type="number" min="0" name="start_count" value="<?= (int)$order['start_count'] ?>" class="w-full h-10 px-3 rounded-xl border border-slate-200 text-xs tabular-nums">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Remains</label>
                        <input type="number" min="0" name="remains" value="<?= (int)$order['remains'] ?>" class="w-full h-10 px-3 rounded-xl border border-slate-200 text-xs tabular-nums">
                    </div>
                </div>
                <button type="submit" class="h-10 px-5 rounded-xl bg-[#151515] text-white text-xs font-semibold">Update Order</button>
            </form>

            <?php if ((int)$order['is_refunded'] === 0 && in_array($order['status'], ['cancelled', 'failed', 'partial'], true)): ?>
                <form method="post" class="mt-4 pt-4 border-t border-slate-100">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="refund">
                    <button type="submit" class="h-10 px-5 rounded-xl bg-rose-600 text-white text-xs font-semibold">Process Eligible Refund</button>
                </form>
            <?php elseif ((int)$order['is_refunded'] === 1): ?>
                <div class="mt-4 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800">
                    Refunded <strong><?= e(format_money($order['refunded_amount'])) ?></strong> to user wallet.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 p-6">
        <h2 class="text-sm font-bold text-[#151515] mb-4">Order Logs</h2>
        <div class="space-y-3">
            <?php foreach ($logs as $log): ?>
                <div class="pb-3 border-b border-slate-100 last:border-0 text-xs">
                    <div class="flex justify-between font-semibold text-[#151515]">
                        <span><?= e(ucwords(str_replace('_', ' ', $log['new_status']))) ?> (<?= e($log['actor_type']) ?>)</span>
                        <span class="text-slate-400 font-normal"><?= e(date('d M, H:i', strtotime($log['created_at']))) ?></span>
                    </div>
                    <p class="text-slate-600 mt-1"><?= e($log['message']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
