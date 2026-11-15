<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$pageTitle = 'FAQ';
include INCLUDES_PATH . '/user-header.php';

$faqs = [
    ['How do I place an order?', 'Add funds to your wallet via Add Funds, choose a service on the New Order or Services page, enter your target URL and quantity, and submit.'],
    ['How are partial or cancelled orders refunded?', 'Eligible cancelled, failed, or partial orders are automatically refunded proportionally to your wallet balance and recorded in your Transactions ledger.'],
    ['How do I connect via API?', 'Open API Access in the sidebar to copy or regenerate your personal API key and view request examples for placing orders and checking statuses.'],
    ['What happens if a provider rejects an order?', 'If an upstream provider fails to accept an order, it is logged for administrator review and eligible orders can be refunded directly to your wallet.'],
];
?>

<div class="max-w-3xl">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515] mb-1">Frequently Asked Questions</h1>
    <p class="text-sm text-slate-500 mb-6">Answers to common questions about ordering, billing, and API integration.</p>

    <div class="space-y-3">
        <?php foreach ($faqs as $idx => [$q, $a]): ?>
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5" x-data="{ open: <?= $idx === 0 ? 'true' : 'false' ?> }">
                <button type="button" @click="open = !open" class="w-full flex items-center justify-between text-left gap-4">
                    <span class="text-sm font-bold text-[#151515]"><?= e($q) ?></span>
                    <span class="text-xs font-semibold text-[#7138D8]" x-text="open ? 'Hide' : 'Show'"></span>
                </button>
                <p x-show="open" class="mt-3 text-sm text-slate-600 leading-relaxed"><?= e($a) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include INCLUDES_PATH . '/user-footer.php'; ?>
