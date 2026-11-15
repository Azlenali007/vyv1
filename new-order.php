<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$user = require_user_login();
$categories = Category::all(true);
$services   = Service::search('', 0, true);
$selectedServiceId = (int)($_GET['service_id'] ?? $_POST['service_id'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $res = Order::place(
        (int)$user['id'],
        (int)($_POST['service_id'] ?? 0),
        (string)($_POST['link'] ?? ''),
        (int)($_POST['quantity'] ?? 0),
        $_POST['comments'] ?? null,
        $_POST['idempotency_key'] ?? null
    );
    if ($res['ok']) {
        set_flash('success', "Order #{$res['order_code']} placed for " . format_money($res['charge']) . '.');
        redirect('/order-details.php?id=' . (int)$res['order_id']);
    }
    $error = $res['error'];
}

$currency = get_setting('currency_symbol', DEFAULT_CURRENCY_SYMBOL);
$idempotencyKey = bin2hex(random_bytes(16));

$servicesPayload = array_map(static fn(array $s) => [
    'id'          => (int)$s['id'],
    'category_id' => (int)$s['category_id'],
    'name'        => $s['name'],
    'rate'        => (float)$s['rate_per_1000'],
    'min'         => (int)$s['min_qty'],
    'max'         => (int)$s['max_qty'],
    'type'        => $s['service_type'],
    'description' => $s['description'] ?? '',
    'avg_time'    => $s['average_time'] ?? '',
], $services);

$pageTitle = 'New Order';
include INCLUDES_PATH . '/user-header.php';
?>

<div class="mb-6">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Place New Order</h1>
    <p class="text-sm text-slate-500 mt-0.5">Select a category and service. Pricing and balance are validated securely on the server.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="mb-5 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-700 font-medium">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<?php if (empty($services)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center">
        <div class="text-base font-semibold text-[#151515]">No Active Services Available</div>
        <p class="text-sm text-slate-500 mt-1">Services must be created or imported by an administrator before orders can be placed.</p>
    </div>
<?php else: ?>
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6"
     x-data="{
        services: <?= e(json_encode($servicesPayload)) ?>,
        selectedCategory: 0,
        selectedServiceId: <?= $selectedServiceId ?: (int)$servicesPayload[0]['id'] ?>,
        quantity: <?= (int)($servicesPayload[0]['min'] ?? 100) ?>,
        currency: <?= e(json_encode($currency)) ?>,
        init() {
            const found = this.services.find(s => s.id === this.selectedServiceId);
            if (found) {
                this.selectedCategory = found.category_id;
                this.quantity = found.min;
            } else if (this.services.length > 0) {
                this.selectedCategory = this.services[0].category_id;
                this.selectedServiceId = this.services[0].id;
                this.quantity = this.services[0].min;
            }
        },
        get filteredServices() {
            if (!this.selectedCategory) return this.services;
            return this.services.filter(s => s.category_id === Number(this.selectedCategory));
        },
        get activeService() {
            return this.services.find(s => s.id === Number(this.selectedServiceId)) || this.filteredServices[0] || null;
        },
        onCategoryChange() {
            const list = this.filteredServices;
            if (list.length > 0) {
                this.selectedServiceId = list[0].id;
                this.quantity = list[0].min;
            }
        },
        onServiceChange() {
            if (this.activeService) {
                this.quantity = this.activeService.min;
            }
        },
        get estimatedCharge() {
            if (!this.activeService) return 0;
            return (this.activeService.rate / 1000) * (Number(this.quantity) || 0);
        }
     }">
    <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
        <form method="post" class="space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="idempotency_key" value="<?= e($idempotencyKey) ?>">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Category</label>
                <select x-model.number="selectedCategory" @change="onCategoryChange()"
                        class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm bg-white focus:outline-none focus:border-[#7138D8]">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="service_id" class="block text-xs font-semibold text-slate-700 mb-1.5">Service</label>
                <select id="service_id" name="service_id" x-model.number="selectedServiceId" @change="onServiceChange()" required
                        class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm bg-white focus:outline-none focus:border-[#7138D8]">
                    <template x-for="svc in filteredServices" :key="svc.id">
                        <option :value="svc.id" x-text="`#${svc.id} — ${svc.name} (${currency}${svc.rate.toFixed(2)}/1k)`"></option>
                    </template>
                </select>
            </div>

            <div>
                <label for="link" class="block text-xs font-semibold text-slate-700 mb-1.5">Target Link or Username</label>
                <input id="link" type="text" name="link" required value="<?= e($_POST['link'] ?? '') ?>"
                       placeholder="https://instagram.com/p/..."
                       class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]">
            </div>

            <div>
                <label for="quantity" class="block text-xs font-semibold text-slate-700 mb-1.5">Quantity</label>
                <input id="quantity" type="number" name="quantity" x-model.number="quantity"
                       :min="activeService ? activeService.min : 1"
                       :max="activeService ? activeService.max : 1000000" required
                       class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm tabular-nums focus:outline-none focus:border-[#7138D8]">
                <div class="mt-1.5 text-xs text-slate-500 tabular-nums" x-show="activeService">
                    Min: <span x-text="activeService?.min.toLocaleString()"></span> · Max: <span x-text="activeService?.max.toLocaleString()"></span>
                </div>
            </div>

            <div x-show="activeService && activeService.type === 'custom_comments'" x-cloak>
                <label for="comments" class="block text-xs font-semibold text-slate-700 mb-1.5">Custom Comments (One per line)</label>
                <textarea id="comments" name="comments" rows="4"
                          class="w-full p-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]"><?= e($_POST['comments'] ?? '') ?></textarea>
            </div>

            <div class="pt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-t border-slate-100">
                <div>
                    <div class="text-xs text-slate-500">Estimated Charge</div>
                    <div class="text-2xl font-bold text-[#151515] tabular-nums" x-text="currency + estimatedCharge.toFixed(2)"></div>
                </div>
                <button type="submit"
                        class="h-11 px-6 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-sm font-semibold smooth-transition">
                    Submit Order
                </button>
            </div>
        </form>
    </div>

    <div class="lg:col-span-4 space-y-4">
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5">
            <div class="text-xs font-semibold text-slate-500">Your Wallet Balance</div>
            <div class="text-2xl font-bold text-[#16A36A] tabular-nums mt-1"><?= e(format_money($user['balance'])) ?></div>
            <a href="/add-funds.php" class="mt-3 inline-flex text-xs font-semibold text-[#7138D8] hover:underline">+ Add Funds to Wallet</a>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/80 p-5" x-show="activeService">
            <h3 class="text-sm font-bold text-[#151515]">Service Details</h3>
            <div class="mt-3 space-y-2.5 text-xs">
                <div class="flex justify-between">
                    <span class="text-slate-500">Rate per 1,000</span>
                    <span class="font-semibold text-[#151515] tabular-nums" x-text="currency + (activeService?.rate || 0).toFixed(2)"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Average Time</span>
                    <span class="font-medium text-slate-700" x-text="activeService?.avg_time || '—'"></span>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-600 leading-relaxed whitespace-pre-line"
                 x-text="activeService?.description || 'No additional description provided.'"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include INCLUDES_PATH . '/user-footer.php'; ?>
