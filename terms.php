<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$siteName = get_setting('site_name', APP_NAME_DEFAULT);
$pageTitle = 'Terms of Service';
include INCLUDES_PATH . '/user-header.php';
?>

<div class="max-w-3xl bg-white rounded-2xl border border-slate-200/80 p-6 sm:p-8 space-y-4">
    <h1 class="text-2xl font-bold text-[#151515]">Terms of Service</h1>
    <p class="text-xs text-slate-400">Last updated: <?= e(get_setting('terms_updated_at', date('Y-m-d'))) ?></p>
    <div class="space-y-3 text-sm text-slate-600 leading-relaxed">
        <p>By creating an account or placing an order on <?= e($siteName) ?>, you agree to use our social media marketing services responsibly and in compliance with applicable platform guidelines.</p>
        <p>All wallet deposits are non-withdrawable credits reserved exclusively for purchasing active catalog services. Eligible cancelled or partial orders are refunded back to your panel wallet balance.</p>
        <p>Target accounts or links submitted for fulfillment must be public during order delivery. Private, deleted, or modified links cannot be fulfilled or guaranteed.</p>
    </div>
</div>

<?php include INCLUDES_PATH . '/user-footer.php'; ?>
