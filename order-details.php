<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$user = require_user_login();
$pdo = Database::getConnection();
$orderId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT o.*, s.name AS service_name, c.name AS category_name, c.platform
     FROM orders o
     INNER JOIN services s ON s.id = o.service_id
     INNER JOIN categories c ON c.id = s.category_id
     WHERE o.id = ? AND o.user_id = ? LIMIT 1"
);
$stmt->execute([$orderId, (int)$user['id']]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('/orders.php');
}

$logsStmt = $pdo->prepare('SELECT * FROM order_logs WHERE order_id = ? ORDER BY id DESC');
$logsStmt->execute([$orderId]);
$logs = $logsStmt->fetchAll();

$meta = order_status_meta($order['status']);
$pageTitle = 'Order #' . $order['order_code'];
include INCLUDES_PATH . '/user-header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <a href="/orders.php" class="text-xs font-semibold text-[#7138D8] hover:underline">&larr; Back to Orders</a>
        <h1 class="text-xl sm:text-2xl font-bold text-[#151515] mt-1 font-mono-code">Order #<?= e($order['order_code']) ?></h1>
    </div>
    <div class="flex items-center gap-2">
        <?php if (!empty($order['provider_order_id'])): ?>
            <button type="button" onclick="window.SMMOrders.checkStatus(<?= (int)$order['id'] ?>, this)"
                    class="h-10 px-4 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-xs font-semibold">
                Check Live Status
            </button>
        <?php endif; ?>
        <a href="/tickets.php?order_id=<?= (int)$order['id'] ?>"
           class="h-10 px-4 inline-flex items-center rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
            Open Support Ticket
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 space-y-4">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <?= platform_icon_svg((string)$order['platform']) ?>
                <div>
                    <div class="font-bold text-[#151515]"><?= e($order['service_name']) ?></div>
                    <div class="text-xs text-slate-500"><?= e($order['category_name']) ?></div>
                </div>
            </div>
            <span class="px-3 py-1 rounded-lg border text-xs font-semibold <?= $meta['bg'] ?>"><?= e($meta['label']) ?></span>
        </div>

        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-xs text-slate-500">Quantity</dt>
                <dd class="font-bold text-[#151515] tabular-nums mt-0.5"><?= number_format((int)$order['quantity']) ?></dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Charge</dt>
                <dd class="font-bold text-[#151515] tabular-nums mt-0.5"><?= e(format_money($order['charge'])) ?></dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Start / Remains</dt>
                <dd class="font-bold text-[#151515] tabular-nums mt-0.5"><?= number_format((int)$order['start_count']) ?> / <?= number_format((int)$order['remains']) ?></dd>
            </div>
            <div class="col-span-2 sm:col-span-3">
                <dt class="text-xs text-slate-500">Target Link</dt>
                <dd class="font-mono-code text-xs text-[#7138D8] break-all mt-0.5"><?= e($order['link']) ?></dd>
            </div>
            <?php if ((int)$order['is_refunded'] === 1): ?>
            <div class="col-span-2 sm:col-span-3 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800">
                Refunded <strong><?= e(format_money($order['refunded_amount'])) ?></strong> back to your wallet.
            </div>
            <?php endif; ?>
        </dl>
    </div>

    <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
        <h2 class="text-sm font-bold text-[#151515] mb-4">Order Activity Timeline</h2>
        <?php if (empty($logs)): ?>
            <p class="text-xs text-slate-500">No status logs recorded yet.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($logs as $log): ?>
                    <div class="pb-3 border-b border-slate-100 last:border-0 last:pb-0">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-[#151515]"><?= e(ucwords(str_replace('_', ' ', $log['new_status']))) ?></span>
                            <span class="text-slate-400"><?= e(date('d M, H:i', strtotime($log['created_at']))) ?></span>
                        </div>
                        <p class="text-xs text-slate-600 mt-1"><?= e($log['message']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include INCLUDES_PATH . '/user-footer.php'; ?>
