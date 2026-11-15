<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin      = require_admin_permission('providers.manage');
$providers  = Provider::all();
$categories = Category::all(false);

$pageTitle = 'Import Services';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="mb-6">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Import Services from Provider</h1>
    <p class="text-sm text-slate-500 mt-0.5">Fetch live service catalogs via PHP cURL, preview pricing with your profit margin, and import selected or all services.</p>
</div>

<?php if (empty($providers)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center">
        <div class="text-base font-semibold text-[#151515]">No Providers Configured</div>
        <p class="text-sm text-slate-500 mt-1">Add an API provider with valid credentials before importing services.</p>
        <a href="/admin/provider-add.php" class="mt-4 inline-flex px-4 py-2.5 rounded-xl bg-[#7138D8] text-white text-xs font-semibold">+ Add Provider</a>
    </div>
<?php else: ?>
<div class="space-y-5"
     x-data="{
        providerId: <?= (int)$providers[0]['id'] ?>,
        marginType: 'percentage',
        marginValue: <?= (float)get_setting('default_margin_value', '30') ?>,
        targetCategoryId: '',
        categoryFilter: '',
        updateExisting: true,
        services: [],
        selectedIds: [],
        loading: false,
        importing: false,
        errorMsg: '',
        resultMsg: '',
        async fetchPreview() {
            this.loading = true;
            this.errorMsg = '';
            this.resultMsg = '';
            this.services = [];
            this.selectedIds = [];
            try {
                const res = await window.SMM.apiPost('/api/provider-import.php', {
                    mode: 'preview',
                    provider_id: this.providerId
                });
                if (res.ok) {
                    this.services = res.services || [];
                } else {
                    this.errorMsg = res.error || 'Could not fetch services from provider.';
                }
            } finally {
                this.loading = false;
            }
        },
        get externalCategories() {
            return [...new Set(this.services.map(s => s.category || 'Uncategorized'))];
        },
        get filteredServices() {
            if (!this.categoryFilter) return this.services;
            return this.services.filter(s => (s.category || 'Uncategorized') === this.categoryFilter);
        },
        calcSelling(rawRate) {
            const r = Number(rawRate || 0);
            if (this.marginType === 'fixed') return (r + Number(this.marginValue || 0)).toFixed(2);
            return (r * (1 + Number(this.marginValue || 0) / 100)).toFixed(2);
        },
        selectFiltered() {
            this.selectedIds = this.filteredServices.map(s => String(s.service ?? s.id));
        },
        deselectAll() {
            this.selectedIds = [];
        },
        async runImport(importAllFiltered = false) {
            const list = importAllFiltered
                ? this.filteredServices
                : this.filteredServices.filter(s => this.selectedIds.includes(String(s.service ?? s.id)));
            if (!list.length) {
                alert('Select at least one service or choose Import All.');
                return;
            }
            this.importing = true;
            this.errorMsg = '';
            this.resultMsg = '';
            try {
                const res = await window.SMM.apiPost('/api/provider-import.php', {
                    mode: 'import',
                    provider_id: this.providerId,
                    margin_type: this.marginType,
                    margin_value: this.marginValue,
                    target_category_id: this.targetCategoryId || null,
                    update_existing: this.updateExisting,
                    services: list
                });
                if (res.ok) {
                    this.resultMsg = `Import complete: ${res.imported} imported, ${res.updated} updated, ${res.skipped} skipped.`;
                } else {
                    this.errorMsg = res.error || 'Import failed.';
                }
            } finally {
                this.importing = false;
            }
        }
     }">

    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Provider</label>
            <select x-model.number="providerId" class="w-full h-10 px-3 rounded-xl border border-slate-200 text-sm bg-white">
                <?php foreach ($providers as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['connection_status']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Margin Type</label>
            <select x-model="marginType" class="w-full h-10 px-3 rounded-xl border border-slate-200 text-sm bg-white">
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed (+)</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Margin Value</label>
            <input type="number" step="0.01" min="0" x-model.number="marginValue" class="w-full h-10 px-3 rounded-xl border border-slate-200 text-sm tabular-nums">
        </div>
        <div>
            <button type="button" @click="fetchPreview()" :disabled="loading"
                    class="w-full h-10 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] disabled:opacity-50 text-white text-xs font-semibold">
                <span x-text="loading ? 'Fetching...' : 'Fetch Live Services'"></span>
            </button>
        </div>
    </div>

    <div x-show="errorMsg" x-cloak class="px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-700 font-medium" x-text="errorMsg"></div>
    <div x-show="resultMsg" x-cloak class="px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm text-emerald-800 font-medium" x-text="resultMsg"></div>

    <div x-show="services.length > 0" x-cloak class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <select x-model="categoryFilter" class="h-9 px-3 rounded-xl border border-slate-200 text-xs bg-white">
                    <option value="">All Provider Categories</option>
                    <template x-for="cat in externalCategories" :key="cat">
                        <option :value="cat" x-text="cat"></option>
                    </template>
                </select>
                <select x-model="targetCategoryId" class="h-9 px-3 rounded-xl border border-slate-200 text-xs bg-white">
                    <option value="">Auto-create categories by provider</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>">Import into: <?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" @click="selectFiltered()" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-medium">Select Visible</button>
                <button type="button" @click="deselectAll()" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-medium">Deselect</button>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="runImport(false)" :disabled="importing"
                        class="h-9 px-4 rounded-xl bg-[#151515] text-white text-xs font-semibold">
                    Import Selected (<span x-text="selectedIds.length"></span>)
                </button>
                <button type="button" @click="runImport(true)" :disabled="importing"
                        class="h-9 px-4 rounded-xl bg-[#16A36A] text-white text-xs font-semibold">
                    Import All Visible (<span x-text="filteredServices.length"></span>)
                </button>
            </div>
        </div>

        <div class="overflow-x-auto max-h-[520px]">
            <table class="w-full text-left border-collapse text-xs">
                <thead class="sticky top-0 bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold">
                    <tr>
                        <th class="px-4 py-3 w-10">Sel</th>
                        <th class="px-4 py-3">Ext ID</th>
                        <th class="px-4 py-3">Service Name & Category</th>
                        <th class="px-4 py-3 text-right">Provider Rate</th>
                        <th class="px-4 py-3 text-right">Selling Preview</th>
                        <th class="px-4 py-3 text-right">Min – Max</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="svc in filteredServices" :key="svc.service ?? svc.id">
                        <tr class="table-row-hover">
                            <td class="px-4 py-2.5">
                                <input type="checkbox" :value="String(svc.service ?? svc.id)" x-model="selectedIds" class="rounded">
                            </td>
                            <td class="px-4 py-2.5 font-mono-code text-slate-500" x-text="'#' + (svc.service ?? svc.id)"></td>
                            <td class="px-4 py-2.5">
                                <div class="font-medium text-[#151515]" x-text="svc.name"></div>
                                <div class="text-slate-400" x-text="svc.category"></div>
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums" x-text="Number(svc.rate || 0).toFixed(2)"></td>
                            <td class="px-4 py-2.5 text-right tabular-nums font-bold text-[#16A36A]" x-text="calcSelling(svc.rate)"></td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-slate-500" x-text="`${svc.min} – ${svc.max}`"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
