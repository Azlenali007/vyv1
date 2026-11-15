<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('services.manage');
$categories = Category::all(false);
$providers  = Provider::all();
$q          = trim((string)($_GET['q'] ?? ''));
$catFilter  = (int)($_GET['category_id'] ?? 0);
$services   = Service::search($q, $catFilter, false);

$pageTitle = 'Services';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Services & Bulk Management</h1>
        <p class="text-sm text-slate-500 mt-0.5">Configure provider costs, profit margins, and perform server-validated bulk updates.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="/admin/import-services.php" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 whitespace-nowrap">Import from Provider</a>
        <a href="/admin/service-add.php" class="px-4 py-2.5 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-xs font-semibold whitespace-nowrap">+ Add Service</a>
    </div>
</div>

<!-- Search & Filter -->
<form method="get" class="bg-white rounded-2xl border border-slate-200/80 p-4 mb-4 flex flex-col sm:flex-row gap-3">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search service name or ID..." class="flex-1 h-10 px-3.5 rounded-xl border border-slate-200 text-sm">
    <select name="category_id" class="h-10 px-3.5 rounded-xl border border-slate-200 text-sm bg-white">
        <option value="0">All Categories</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $catFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="h-10 px-5 rounded-xl bg-[#151515] text-white text-xs font-semibold">Filter</button>
</form>

<!-- Server-Validated Bulk Action Toolbar -->
<div class="bg-white rounded-2xl border border-slate-200/80 p-4 mb-4 flex flex-wrap items-center gap-2.5"
     x-data="{
        action: 'enable',
        categoryId: '',
        providerId: '',
        marginType: 'percentage',
        marginValue: 30,
        busy: false,
        async applyBulk() {
            const ids = window.SMMAdmin.getSelectedIds('.svc-checkbox');
            if (!ids.length) { alert('Select at least one service first.'); return; }
            if (this.action === 'delete' && !confirm(`Delete ${ids.length} selected service(s)?`)) return;
            this.busy = true;
            try {
                const res = await window.SMM.apiPost('/api/bulk-operations.php', {
                    entity: 'services',
                    action: this.action,
                    ids,
                    category_id: this.categoryId,
                    provider_id: this.providerId,
                    margin_type: this.marginType,
                    margin_value: this.marginValue
                });
                if (res.ok) {
                    window.location.reload();
                } else {
                    alert(res.error || 'Bulk operation failed.');
                }
            } finally {
                this.busy = false;
            }
        }
     }">
    <div class="text-xs font-semibold text-slate-600 mr-2">
        Selected: <span id="bulkSelectedCount" class="font-bold text-[#7138D8] tabular-nums">0</span>
    </div>
    <button type="button" onclick="window.SMMAdmin.selectAll(true, '.svc-checkbox')" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs font-medium hover:bg-slate-50">Select All</button>
    <button type="button" onclick="window.SMMAdmin.selectAll(false, '.svc-checkbox')" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs font-medium hover:bg-slate-50">Deselect All</button>

    <select x-model="action" class="h-9 px-3 rounded-xl border border-slate-200 text-xs bg-white font-semibold">
        <option value="enable">Bulk Enable</option>
        <option value="disable">Bulk Disable</option>
        <option value="adjust_margin">Bulk Adjust Margin / Price</option>
        <option value="assign_category">Bulk Assign Category</option>
        <option value="assign_provider">Bulk Assign Provider</option>
        <option value="delete">Bulk Delete</option>
    </select>

    <template x-if="action === 'assign_category'">
        <select x-model="categoryId" class="h-9 px-3 rounded-xl border border-slate-200 text-xs bg-white">
            <option value="">Choose Category...</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </template>

    <template x-if="action === 'assign_provider'">
        <select x-model="providerId" class="h-9 px-3 rounded-xl border border-slate-200 text-xs bg-white">
            <option value="">None (Manual)</option>
            <?php foreach ($providers as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </template>

    <template x-if="action === 'adjust_margin'">
        <div class="flex items-center gap-2">
            <select x-model="marginType" class="h-9 px-2.5 rounded-xl border border-slate-200 text-xs bg-white">
                <option value="percentage">% Margin</option>
                <option value="fixed">Fixed +</option>
            </select>
            <input type="number" step="0.01" min="0" x-model.number="marginValue" class="w-24 h-9 px-2.5 rounded-xl border border-slate-200 text-xs tabular-nums">
        </div>
    </template>

    <button type="button" @click="applyBulk()" :disabled="busy"
            class="h-9 px-4 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] disabled:opacity-50 text-white text-xs font-semibold">
        <span x-text="busy ? 'Applying...' : 'Apply Bulk Action'"></span>
    </button>
</div>

<div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3.5 w-10">
                        <input id="selectAllCheckbox" type="checkbox" onchange="window.SMMAdmin.toggleAll(this, '.svc-checkbox')" class="rounded">
                    </th>
                    <th class="px-4 py-3.5">ID</th>
                    <th class="px-4 py-3.5">Service & Category</th>
                    <th class="px-4 py-3.5">Provider</th>
                    <th class="px-4 py-3.5 text-right">Cost / 1k</th>
                    <th class="px-4 py-3.5 text-right">Margin</th>
                    <th class="px-4 py-3.5 text-right">Selling / 1k</th>
                    <th class="px-4 py-3.5">Status</th>
                    <th class="px-4 py-3.5 text-right">Edit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($services)): ?>
                    <tr><td colspan="9" class="px-5 py-12 text-center text-sm text-slate-500">No services found. Add a service manually or import from an API provider.</td></tr>
                <?php else: ?>
                    <?php foreach ($services as $s): ?>
                        <tr class="table-row-hover">
                            <td class="px-4 py-3">
                                <input type="checkbox" value="<?= (int)$s['id'] ?>" onchange="window.SMMAdmin.updateSelectedCount('.svc-checkbox')" class="svc-checkbox rounded">
                            </td>
                            <td class="px-4 py-3 font-mono-code text-xs text-slate-500">#<?= (int)$s['id'] ?></td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-[#151515]"><?= e($s['name']) ?></div>
                                <div class="text-xs text-slate-500"><?= e($s['category_name']) ?></div>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                <?= e($s['provider_name'] ?: 'Manual') ?>
                                <?php if (!empty($s['provider_service_id'])): ?>
                                    <span class="font-mono-code text-slate-400">(#<?= e($s['provider_service_id']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-xs text-slate-600"><?= e(format_rate($s['provider_rate'])) ?></td>
                            <td class="px-4 py-3 text-right tabular-nums text-xs text-slate-600">
                                <?= $s['margin_type'] === 'fixed' ? '+' . e(format_money($s['margin_value'])) : e(number_format((float)$s['margin_value'], 1)) . '%' ?>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-bold text-[#151515]"><?= e(format_rate($s['rate_per_1000'])) ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-lg border text-xs font-semibold <?= $s['status'] === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200' ?>">
                                    <?= e(ucfirst($s['status'])) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="/admin/service-edit.php?id=<?= (int)$s['id'] ?>" class="px-2.5 py-1.5 rounded-lg bg-[#7138D8]/10 text-[#7138D8] text-xs font-semibold">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
