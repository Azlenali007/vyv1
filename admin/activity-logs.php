<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('logs.view');
$pdo   = Database::getConnection();

$activityLogs = $pdo->query('SELECT * FROM activity_logs ORDER BY id DESC LIMIT 80')->fetchAll();
$apiLogs      = $pdo->query('SELECT * FROM api_logs ORDER BY id DESC LIMIT 50')->fetchAll();

$pageTitle = 'Activity & API Logs';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="mb-6">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Activity & API Logs</h1>
    <p class="text-sm text-slate-500 mt-0.5">Audit trail of user/admin actions and outbound cURL provider communication.</p>
</div>

<div class="space-y-6">
    <div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 font-bold text-sm text-[#151515]">System Activity Logs</div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-slate-500 font-semibold">
                        <th class="px-4 py-3">Actor</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Details</th>
                        <th class="px-4 py-3">IP</th>
                        <th class="px-4 py-3 text-right">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($activityLogs)): ?>
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No activity logs recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($activityLogs as $l): ?>
                            <tr>
                                <td class="px-4 py-2.5 font-semibold"><?= e($l['actor_type']) ?><?= $l['actor_id'] ? ' #' . (int)$l['actor_id'] : '' ?></td>
                                <td class="px-4 py-2.5 font-mono-code text-[#7138D8]"><?= e($l['action']) ?></td>
                                <td class="px-4 py-2.5 text-slate-600 max-w-md truncate"><?= e($l['details'] ?: '—') ?></td>
                                <td class="px-4 py-2.5 font-mono-code text-slate-500"><?= e($l['ip_address'] ?: '—') ?></td>
                                <td class="px-4 py-2.5 text-right text-slate-400 whitespace-nowrap"><?= e(date('d M, H:i:s', strtotime($l['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 font-bold text-sm text-[#151515]">API Request Logs</div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-slate-500 font-semibold">
                        <th class="px-4 py-3">Direction</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Endpoint</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($apiLogs)): ?>
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No API requests logged yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($apiLogs as $al): ?>
                            <tr>
                                <td class="px-4 py-2.5 font-semibold"><?= e($al['direction']) ?></td>
                                <td class="px-4 py-2.5 font-mono-code"><?= e($al['action']) ?></td>
                                <td class="px-4 py-2.5 font-mono-code text-slate-500 max-w-xs truncate"><?= e($al['endpoint']) ?></td>
                                <td class="px-4 py-2.5">
                                    <span class="font-semibold <?= $al['is_success'] ? 'text-[#16A36A]' : 'text-rose-600' ?>">
                                        <?= $al['is_success'] ? 'OK' : 'ERR' ?> (HTTP <?= (int)$al['http_code'] ?>)
                                    </span>
                                    <?php if (!empty($al['error_message'])): ?>
                                        <span class="text-slate-400 ml-1"><?= e($al['error_message']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2.5 text-right text-slate-400 whitespace-nowrap"><?= e(date('d M, H:i:s', strtotime($al['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
