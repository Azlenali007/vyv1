<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin      = require_admin_permission('services.manage');
$categories = Category::all(false);
$providers  = Provider::all();
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $res = Service::create($_POST);
    if ($res['ok']) {
        log_activity('admin', (int)$admin['id'], 'service.create', 'service', (int)$res['id'], $_POST['name'] ?? '');
        set_flash('success', 'Service created.');
        redirect('/admin/services.php');
    }
    $error = $res['error'];
}

$pageTitle = 'Add Service';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="max-w-2xl">
    <a href="/admin/services.php" class="text-xs font-semibold text-[#7138D8] hover:underline">&larr; Back to Services</a>
    <h1 class="text-xl font-bold text-[#151515] mt-1 mb-5">Add Service</h1>

    <?php if ($error !== ''): ?>
        <div class="mb-4 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-700 font-medium"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (empty($categories)): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 text-sm text-amber-800">
            Create at least one category first on the <a href="/admin/categories.php" class="font-bold underline">Categories page</a>.
        </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-200/80 p-6"
         x-data="{
            cost: 100,
            mType: 'percentage',
            mVal: 30,
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
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Service Type</label>
                    <select name="service_type" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm bg-white">
                        <option value="default">Default</option>
                        <option value="custom_comments">Custom Comments</option>
                        <option value="package">Package</option>
                        <option value="drip_feed">Drip Feed</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Service Name</label>
                <input type="text" name="name" required class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">API Provider (Optional)</label>
                    <select name="provider_id" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm bg-white">
                        <option value="">Manual Fulfillment</option>
                        <?php foreach ($providers as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Provider Service ID</label>
                    <input type="text" name="provider_service_id" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm font-mono-code">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Provider Cost / 1k</label>
                    <input type="number" step="0.0001" min="0" name="provider_rate" x-model.number="cost" required class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm tabular-nums">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Margin Type</label>
                    <select name="margin_type" x-model="mType" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm bg-white">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount (+)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Margin Value</label>
                    <input type="number" step="0.01" min="0" name="margin_value" x-model.number="mVal" required class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm tabular-nums">
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-purple-50 border border-purple-200 text-xs text-purple-900 flex items-center justify-between">
                <span>Calculated Customer Selling Price / 1,000:</span>
                <strong class="text-base tabular-nums" x-text="'<?= e(get_setting('currency_symbol', DEFAULT_CURRENCY_SYMBOL)) ?>' + selling.toFixed(2)"></strong>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Min Quantity</label>
                    <input type="number" min="1" name="min_qty" value="10" required class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm tabular-nums">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Max Quantity</label>
                    <input type="number" min="1" name="max_qty" value="100000" required class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm tabular-nums">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Average Time</label>
                    <input type="text" name="average_time" placeholder="e.g. 15 mins" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Description</label>
                <textarea name="description" rows="3" class="w-full p-3.5 rounded-xl border border-slate-200 text-sm"></textarea>
            </div>

            <div class="flex flex-wrap gap-5 text-xs font-medium text-slate-700">
                <label class="flex items-center gap-2"><input type="checkbox" name="refill_supported" value="1"> Refill Supported</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="cancel_supported" value="1"> Cancel Supported</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="dripfeed_supported" value="1"> Drip-Feed Supported</label>
            </div>

            <button type="submit" class="h-11 px-6 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-sm font-semibold">Create Service</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
