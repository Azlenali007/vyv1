<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('settings.manage');
$pdo   = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $section = $_POST['section'] ?? 'general';

    if ($section === 'general') {
        $keys = [
            'site_name'            => ['group' => 'general',  'val' => clean_text($_POST['site_name'] ?? 'SMM Panel', 100)],
            'site_tagline'         => ['group' => 'general',  'val' => clean_text($_POST['site_tagline'] ?? '', 250)],
            'support_email'        => ['group' => 'general',  'val' => clean_text($_POST['support_email'] ?? '', 160)],
            'currency_symbol'      => ['group' => 'currency', 'val' => clean_text($_POST['currency_symbol'] ?? '₹', 6)],
            'currency_code'        => ['group' => 'currency', 'val' => strtoupper(clean_text($_POST['currency_code'] ?? 'INR', 8))],
            'default_margin_type'  => ['group' => 'pricing',  'val' => ($_POST['default_margin_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage'],
            'default_margin_value' => ['group' => 'pricing',  'val' => (string)max(0, (float)($_POST['default_margin_value'] ?? 30))],
            'registration_enabled' => ['group' => 'security', 'val' => !empty($_POST['registration_enabled']) ? '1' : '0'],
            'maintenance_mode'     => ['group' => 'security', 'val' => !empty($_POST['maintenance_mode']) ? '1' : '0'],
        ];
        foreach ($keys as $k => $meta) {
            set_setting($k, $meta['val'], $meta['group']);
        }
        log_activity('admin', (int)$admin['id'], 'settings.update', 'settings', null, 'Updated general panel settings');
        set_flash('success', 'Website settings saved.');
    } elseif ($section === 'payment_method') {
        $pmId = (int)($_POST['pm_id'] ?? 0);
        $name = clean_text($_POST['pm_name'] ?? '', 100);
        $inst = trim((string)($_POST['pm_instructions'] ?? ''));
        $min  = max(1, (float)($_POST['pm_min'] ?? 10));
        $max  = max($min, (float)($_POST['pm_max'] ?? 100000));
        $bonus = max(0, (float)($_POST['pm_bonus'] ?? 0));
        $status = ($_POST['pm_status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if ($pmId > 0 && $name !== '') {
            $pdo->prepare('UPDATE payment_methods SET name = ?, instructions = ?, min_amount = ?, max_amount = ?, bonus_percent = ?, status = ? WHERE id = ?')
                ->execute([$name, $inst, $min, $max, $bonus, $status, $pmId]);
            set_flash('success', 'Payment method updated.');
        }
    }

    redirect('/admin/settings.php');
}

$settings = get_all_settings();
$paymentMethods = $pdo->query('SELECT * FROM payment_methods ORDER BY sort_order ASC, id ASC')->fetchAll();

$pageTitle = 'Settings';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="mb-6">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Website & Payment Settings</h1>
    <p class="text-sm text-slate-500 mt-0.5">Configure branding, currency, default profit margins, and deposit instructions.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-6 bg-white rounded-2xl border border-slate-200/80 p-6">
        <h2 class="text-base font-bold text-[#151515] mb-4">General & Pricing Configuration</h2>
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="general">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Panel Name</label>
                <input type="text" name="site_name" required value="<?= e($settings['site_name'] ?? 'SMM Panel') ?>" class="w-full h-10 px-3.5 rounded-xl border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Tagline</label>
                <input type="text" name="site_tagline" value="<?= e($settings['site_tagline'] ?? '') ?>" class="w-full h-10 px-3.5 rounded-xl border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Support Email</label>
                <input type="email" name="support_email" value="<?= e($settings['support_email'] ?? '') ?>" class="w-full h-10 px-3.5 rounded-xl border border-slate-200 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Currency Symbol</label>
                    <input type="text" name="currency_symbol" value="<?= e($settings['currency_symbol'] ?? '₹') ?>" class="w-full h-10 px-3.5 rounded-xl border border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Currency Code</label>
                    <input type="text" name="currency_code" value="<?= e($settings['currency_code'] ?? 'INR') ?>" class="w-full h-10 px-3.5 rounded-xl border border-slate-200 text-sm uppercase">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Default Margin Type</label>
                    <select name="default_margin_type" class="w-full h-10 px-3 rounded-xl border border-slate-200 text-sm bg-white">
                        <option value="percentage" <?= ($settings['default_margin_type'] ?? 'percentage') === 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                        <option value="fixed" <?= ($settings['default_margin_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed (+)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Default Margin Value</label>
                    <input type="number" step="0.01" name="default_margin_value" value="<?= e($settings['default_margin_value'] ?? '30') ?>" class="w-full h-10 px-3.5 rounded-xl border border-slate-200 text-sm tabular-nums">
                </div>
            </div>
            <div class="flex flex-wrap gap-5 text-xs font-medium text-slate-700 pt-1">
                <label class="flex items-center gap-2"><input type="checkbox" name="registration_enabled" value="1" <?= ($settings['registration_enabled'] ?? '1') === '1' ? 'checked' : '' ?>> Allow New User Registration</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="maintenance_mode" value="1" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>> Maintenance Mode</label>
            </div>
            <button type="submit" class="h-10 px-5 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-xs font-semibold">Save Settings</button>
        </form>
    </div>

    <div class="lg:col-span-6 space-y-4">
        <?php foreach ($paymentMethods as $pm): ?>
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5">
                <form method="post" class="space-y-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="payment_method">
                    <input type="hidden" name="pm_id" value="<?= (int)$pm['id'] ?>">
                    <div class="flex items-center justify-between gap-3">
                        <input type="text" name="pm_name" value="<?= e($pm['name']) ?>" class="flex-1 h-9 px-3 rounded-xl border border-slate-200 text-sm font-semibold">
                        <select name="pm_status" class="h-9 px-3 rounded-xl border border-slate-200 text-xs bg-white">
                            <option value="active" <?= $pm['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $pm['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <textarea name="pm_instructions" rows="2" class="w-full p-3 rounded-xl border border-slate-200 text-xs"><?= e($pm['instructions']) ?></textarea>
                    <div class="grid grid-cols-3 gap-2">
                        <input type="number" step="0.01" name="pm_min" value="<?= e($pm['min_amount']) ?>" class="h-9 px-3 rounded-xl border border-slate-200 text-xs tabular-nums" title="Min">
                        <input type="number" step="0.01" name="pm_max" value="<?= e($pm['max_amount']) ?>" class="h-9 px-3 rounded-xl border border-slate-200 text-xs tabular-nums" title="Max">
                        <input type="number" step="0.01" name="pm_bonus" value="<?= e($pm['bonus_percent']) ?>" class="h-9 px-3 rounded-xl border border-slate-200 text-xs tabular-nums" title="Bonus %">
                    </div>
                    <button type="submit" class="h-9 px-4 rounded-xl bg-[#151515] text-white text-xs font-semibold">Update Method</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
