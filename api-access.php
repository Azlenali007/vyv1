<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$user = require_user_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'regenerate_key') {
        User::regenerateApiKey((int)$user['id']);
        set_flash('success', 'New API key generated.');
        redirect('/api-access.php');
    }
}

$endpointUrl = app_base_url() . '/api/new-order.php';
$pageTitle = 'API Access';
include INCLUDES_PATH . '/user-header.php';
?>

<div class="mb-6">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">API Access</h1>
    <p class="text-sm text-slate-500 mt-0.5">Integrate your storefront or scripts directly with our REST JSON API.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 space-y-4">
        <h2 class="text-base font-bold text-[#151515]">Your API Key</h2>
        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 font-mono-code text-xs break-all text-[#151515]">
            <?= e($user['api_key'] ?: 'Not generated yet') ?>
        </div>
        <div class="flex items-center gap-3">
            <?php if (!empty($user['api_key'])): ?>
                <button type="button" onclick="window.SMM.copyText('<?= e($user['api_key']) ?>', this)"
                        class="h-10 px-4 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Copy Key
                </button>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('Regenerating your API key will invalidate the previous key immediately. Continue?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="regenerate_key">
                <button type="submit" class="h-10 px-4 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-xs font-semibold">
                    Regenerate Key
                </button>
            </form>
        </div>
    </div>

    <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 space-y-4">
        <h2 class="text-base font-bold text-[#151515]">REST API Endpoints</h2>
        <div class="space-y-3 text-xs">
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                <div class="font-bold text-[#151515]">1. Place Order (POST /api/new-order.php)</div>
                <pre class="mt-2 font-mono-code text-[11px] text-slate-700 overflow-x-auto">curl -X POST <?= e($endpointUrl) ?> \
  -d "key=YOUR_API_KEY" \
  -d "service_id=1" \
  -d "link=https://instagram.com/username" \
  -d "quantity=1000"</pre>
            </div>
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                <div class="font-bold text-[#151515]">2. Check Order Status (GET /api/order-status.php)</div>
                <pre class="mt-2 font-mono-code text-[11px] text-slate-700 overflow-x-auto">GET <?= e(app_base_url()) ?>/api/order-status.php?key=YOUR_API_KEY&amp;order_id=1</pre>
            </div>
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                <div class="font-bold text-[#151515]">3. Wallet Balance (GET /api/wallet.php)</div>
                <pre class="mt-2 font-mono-code text-[11px] text-slate-700 overflow-x-auto">GET <?= e(app_base_url()) ?>/api/wallet.php?key=YOUR_API_KEY</pre>
            </div>
        </div>
    </div>
</div>

<?php include INCLUDES_PATH . '/user-footer.php'; ?>
