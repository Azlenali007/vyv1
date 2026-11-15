<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('categories.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $res = Category::create($_POST);
        set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Category created.' : $res['error']);
    } elseif ($action === 'update') {
        $res = Category::update((int)($_POST['id'] ?? 0), $_POST);
        set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Category updated.' : $res['error']);
    }
    redirect('/admin/categories.php');
}

$categories = Category::all(false);
$pageTitle  = 'Categories';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="mb-6">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Categories & Bulk Operations</h1>
    <p class="text-sm text-slate-500 mt-0.5">Organize services by platform and configure default category-wise margins.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-4 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 h-fit">
        <h2 class="text-base font-bold text-[#151515] mb-4">Add Category</h2>
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Category Name</label>
                <input type="text" name="name" required placeholder="e.g. Instagram Followers" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Platform</label>
                <select name="platform" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm bg-white">
                    <option value="instagram">Instagram</option>
                    <option value="youtube">YouTube</option>
                    <option value="tiktok">TikTok</option>
                    <option value="facebook">Facebook</option>
                    <option value="telegram">Telegram</option>
                    <option value="twitter">Twitter / X</option>
                    <option value="general">General</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Default Margin Type</label>
                    <select name="default_margin_type" class="w-full h-11 px-3 rounded-xl border border-slate-200 text-xs bg-white">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed (+)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Margin Value</label>
                    <input type="number" step="0.01" min="0" name="default_margin_value" value="30" class="w-full h-11 px-3 rounded-xl border border-slate-200 text-sm tabular-nums">
                </div>
            </div>
            <button type="submit" class="w-full h-11 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-sm font-semibold">Create Category</button>
        </form>
    </div>

    <div class="lg:col-span-8 space-y-4">
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 flex flex-wrap items-center gap-2.5"
             x-data="{
                action: 'enable',
                async apply() {
                    const ids = window.SMMAdmin.getSelectedIds('.cat-checkbox');
                    if (!ids.length) { alert('Select at least one category.'); return; }
                    if (this.action === 'delete' && !confirm(`Delete ${ids.length} selected category(s)?`)) return;
                    const res = await window.SMM.apiPost('/api/bulk-operations.php', { entity: 'categories', action: this.action, ids });
                    if (res.ok) window.location.reload();
                    else alert(res.error || 'Operation failed');
                }
             }">
            <span class="text-xs font-semibold text-slate-600 mr-2">Selected: <strong id="bulkSelectedCount" class="text-[#7138D8]">0</strong></span>
            <button type="button" onclick="window.SMMAdmin.selectAll(true, '.cat-checkbox')" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs">Select All</button>
            <button type="button" onclick="window.SMMAdmin.selectAll(false, '.cat-checkbox')" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs">Deselect All</button>
            <select x-model="action" class="h-9 px-3 rounded-xl border border-slate-200 text-xs bg-white font-semibold">
                <option value="enable">Bulk Enable</option>
                <option value="disable">Bulk Disable</option>
                <option value="delete">Bulk Delete</option>
            </select>
            <button type="button" @click="apply()" class="h-9 px-4 rounded-xl bg-[#151515] text-white text-xs font-semibold">Apply</button>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold text-slate-500">
                        <th class="px-4 py-3.5 w-10"><input id="selectAllCheckbox" type="checkbox" onchange="window.SMMAdmin.toggleAll(this, '.cat-checkbox')" class="rounded"></th>
                        <th class="px-4 py-3.5">Category</th>
                        <th class="px-4 py-3.5 text-right">Services</th>
                        <th class="px-4 py-3.5 text-right">Default Margin</th>
                        <th class="px-4 py-3.5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">No categories created yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categories as $c): ?>
                            <tr class="table-row-hover">
                                <td class="px-4 py-3"><input type="checkbox" value="<?= (int)$c['id'] ?>" onchange="window.SMMAdmin.updateSelectedCount('.cat-checkbox')" class="cat-checkbox rounded"></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <?= platform_icon_svg((string)$c['platform']) ?>
                                        <div>
                                            <div class="font-semibold text-[#151515]"><?= e($c['name']) ?></div>
                                            <div class="text-xs text-slate-400"><?= e(ucfirst($c['platform'])) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium"><?= (int)$c['service_count'] ?></td>
                                <td class="px-4 py-3 text-right tabular-nums text-xs text-slate-600">
                                    <?= $c['default_margin_type'] === 'fixed' ? '+' . e(format_money($c['default_margin_value'])) : e(number_format((float)$c['default_margin_value'], 1)) . '%' ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-lg border text-xs font-semibold <?= $c['status'] === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200' ?>">
                                        <?= e(ucfirst($c['status'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
