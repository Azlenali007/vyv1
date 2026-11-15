<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin   = require_admin_permission('services.manage');
$id      = (int)($_GET['id'] ?? 0);
$service = Service::find($id);

if (!$service) {
    set_flash('error', 'Service not found.');
    redirect('/admin/services.php');
}

$categories = Category::all(false);
$providers  = Provider::all();
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $res = Service::update($id, $_POST);
    if ($res['ok']) {
        log_activity('admin', (int)$admin['id'], 'service.update', 'service', $id, $_POST['name'] ?? '');
        set_flash('success', 'Service updated.');
        redirect('/admin/services.php');
    }
    $error = $res['error'];
}

$pageTitle = 'Edit Service #' . $id;
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="max-w-2xl">
    <a href="/admin/services.php" class="text-xs font-semibold text-[#7138D8] hover:underline">&larr; Back to Services</a>
    <h1 class="text-xl font-bold text-[#151515] mt-1 mb-5">Edit Service #<?= $id ?></h1>

    <?php if ($error !== ''): ?>
        <div class="mb-4 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-700 font-medium"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-slate-200/80 p-6"
         x-data="{
            cost: <?= (float)$service['provider_rate'] ?>,
            mType: '<?= e($service['margin_type']) ?>',
            mVal: <?= (float)$service['margin_value'] ?>,
            get selling() {
                if (this.mType === 'fixed') return (Number(this.cost) || 0) + (Number(this.mVal) || 0);
                return (Number(this.cost) || 0) * (1 + ((Number(this.mVal) || 0) / 100));
            }
         }">
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Category</label>
                    <select name="category_id" required class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm bg-white">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (int)$service['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Status</label>
                    <select name="status" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm bg-white">
                        <option value="active" <?= $service['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $service['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Service Name</label>
                <input type="text" name="name" required value="<?= e($service['name']) ?>" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">API Provider</label>
                    <select name="provider_id" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm bg-white">
                        <option value="">Manual Fulfillment</option>
                        <?php foreach ($providers as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= (int)$service['provider_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Provider Service ID</label>
                    <input type="text" name="provider_service_id" value="<?= e($service['provider_service_id'] ?? '') ?>" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm font-mono-code">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Provider Cost / 1k</label>
                    <input type="number" step="0.0001" min="0" name="provider_rate" x-model.number="cost" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm tabular-nums">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Margin Type</label>
                    <select name="margin_type" x-model="mType" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm bg-white">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed (+)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Margin Value</label>
                    <input type="number" step="0.01" min="0" name="margin_value" x-model.number="mVal" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm tabular-nums">
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-purple-50 border border-purple-200 text-xs text-purple-900 flex items-center justify-between">
                <span>Calculated Customer Selling Price / 1,000:</span>
                <strong class="text-base tabular-nums" x-text="'<?= e(get_setting('currency_symbol', DEFAULT_CURRENCY_SYMBOL)) ?>' + selling.toFixed(2)"></strong>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Min Qty</label>
                    <input type="number" min="1" name="min_qty" value="<?= (int)$service['min_qty'] ?>" required class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm tabular-nums">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Max Qty</label>
                    <input type="number" min="1" name="max_qty" value="<?= (int)$service['max_qty'] ?>" required class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm tabular-nums">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Avg Time</label>
                    <input type="text" name="average_time" value="<?= e($service['average_time'] ?? '') ?>" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Description</label>
                <textarea name="description" rows="3" class="w-full p-3.5 rounded-xl border border-slate-200 text-sm"><?= e($service['description'] ?? '') ?></textarea>
            </div>

            <div class="flex flex-wrap gap-5 text-xs font-medium text-slate-700">
                <label class="flex items-center gap-2"><input type="checkbox" name="refill_supported" value="1" <?= $service['refill_supported'] ? 'checked' : '' ?>> Refill</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="cancel_supported" value="1" <?= $service['cancel_supported'] ? 'checked' : '' ?>> Cancel</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="dripfeed_supported" value="1" <?= $service['dripfeed_supported'] ? 'checked' : '' ?>> Drip-Feed</label>
            </div>

            <button type="submit" class="h-11 px-6 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-sm font-semibold">Save Service</button>
        </form>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
