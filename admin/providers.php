<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('providers.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sync_prices') {
    require_valid_csrf();
    $res = Provider::syncPrices((int)($_POST['provider_id'] ?? 0));
    set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? "Synchronized {$res['updated']} service prices." : $res['error']);
    redirect('/admin/providers.php');
}

$providers = Provider::all();
$pageTitle = 'API Providers';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">API Providers</h1>
        <p class="text-sm text-slate-500 mt-0.5">Connect external SMM API v2 providers using live cURL verification.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="/admin/import-services.php" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700">Import Services</a>
        <a href="/admin/provider-add.php" class="px-4 py-2.5 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-xs font-semibold">+ Add Provider</a>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden"
     x-data="{
        async testConn(id, btn) {
            btn.disabled = true;
            const orig = btn.textContent;
            btn.textContent = 'Testing...';
            try {
                const res = await window.SMM.apiPost('/api/provider-test.php', { provider_id: id });
                if (res.ok) {
                    alert(`Connected! Balance: ${res.balance} ${res.currency} (${res.duration_ms}ms)`);
                    window.location.reload();
                } else {
                    alert('Verification failed: ' + (res.error || 'Unknown error'));
                    window.location.reload();
                }
            } finally {
                btn.disabled = false;
                btn.textContent = orig;
            }
        }
     }">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3.5">Provider</th>
                    <th class="px-4 py-3.5">API Endpoint</th>
                    <th class="px-4 py-3.5 text-right">Balance</th>
                    <th class="px-4 py-3.5 text-right">Linked Services</th>
                    <th class="px-4 py-3.5">Connection</th>
                    <th class="px-4 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($providers)): ?>
                    <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">No API providers configured. Add a provider with valid API credentials to begin importing services.</td></tr>
                <?php else: ?>
                    <?php foreach ($providers as $p):
                        $connCls = match ($p['connection_status']) {
                            'connected' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'error'     => 'bg-rose-50 text-rose-700 border-rose-200',
                            default     => 'bg-amber-50 text-amber-700 border-amber-200',
                        };
                    ?>
                        <tr class="table-row-hover">
                            <td class="px-4 py-3.5 font-semibold text-[#151515]"><?= e($p['name']) ?></td>
                            <td class="px-4 py-3.5 font-mono-code text-xs text-slate-500 truncate max-w-[240px]"><?= e($p['api_url']) ?></td>
                            <td class="px-4 py-3.5 text-right tabular-nums font-semibold">
                                <?= $p['balance'] !== null ? e(number_format((float)$p['balance'], 2) . ' ' . $p['currency']) : 'Unverified' ?>
                            </td>
                            <td class="px-4 py-3.5 text-right tabular-nums"><?= (int)$p['linked_services'] ?></td>
                            <td class="px-4 py-3.5">
                                <span class="px-2.5 py-0.5 rounded-lg border text-xs font-semibold <?= $connCls ?>"><?= e(ucfirst($p['connection_status'])) ?></span>
                                <?php if (!empty($p['last_error'])): ?>
                                    <div class="text-[11px] text-rose-600 mt-1 max-w-[220px] truncate" title="<?= e($p['last_error']) ?>"><?= e($p['last_error']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3.5 text-right whitespace-nowrap space-x-1.5">
                                <button type="button" @click="testConn(<?= (int)$p['id'] ?>, $el)"
                                        class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    Test cURL
                                </button>
                                <form method="post" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="sync_prices">
                                    <input type="hidden" name="provider_id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50">Sync Prices</button>
                                </form>
                                <a href="/admin/provider-edit.php?id=<?= (int)$p['id'] ?>" class="px-2.5 py-1.5 rounded-lg bg-[#7138D8]/10 text-[#7138D8] text-xs font-semibold">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
