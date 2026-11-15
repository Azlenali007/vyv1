<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin    = require_admin_permission('providers.manage');
$id       = (int)($_GET['id'] ?? 0);
$provider = Provider::find($id);

if (!$provider) {
    set_flash('error', 'Provider not found.');
    redirect('/admin/providers.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $res = Provider::update($id, $_POST);
    if ($res['ok']) {
        log_activity('admin', (int)$admin['id'], 'provider.update', 'provider', $id, $_POST['name'] ?? '');
        set_flash('success', 'Provider configuration updated.');
        redirect('/admin/providers.php');
    }
    $error = $res['error'];
}

$pageTitle = 'Edit Provider · ' . $provider['name'];
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="max-w-xl">
    <a href="/admin/providers.php" class="text-xs font-semibold text-[#7138D8] hover:underline">&larr; Back to Providers</a>
    <h1 class="text-xl font-bold text-[#151515] mt-1 mb-5">Edit Provider</h1>

    <?php if ($error !== ''): ?>
        <div class="mb-4 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-700 font-medium"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-slate-200/80 p-6">
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Provider Name</label>
                <input type="text" name="name" required value="<?= e($provider['name']) ?>" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">API Endpoint URL</label>
                <input type="url" name="api_url" required value="<?= e($provider['api_url']) ?>" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm font-mono-code">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">API Key (Leave blank to keep stored key)</label>
                <input type="password" name="api_key" placeholder="••••••••••••" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm font-mono-code">
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Currency</label>
                    <input type="text" name="currency" value="<?= e($provider['currency']) ?>" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm uppercase">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Exchange Rate</label>
                    <input type="number" step="0.000001" name="exchange_rate" value="<?= e($provider['exchange_rate']) ?>" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm tabular-nums">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Status</label>
                    <select name="status" class="w-full h-11 px-3 rounded-xl border border-slate-200 text-sm bg-white">
                        <option value="active" <?= $provider['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $provider['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="h-11 px-6 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-sm font-semibold">Save Provider</button>
        </form>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
