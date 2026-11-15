<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$siteName = get_setting('site_name', APP_NAME_DEFAULT);
$pageTitle = 'Privacy Policy';
include INCLUDES_PATH . '/user-header.php';
?>

<div class="max-w-3xl bg-white rounded-2xl border border-slate-200/80 p-6 sm:p-8 space-y-4">
    <h1 class="text-2xl font-bold text-[#151515]">Privacy Policy</h1>
    <p class="text-xs text-slate-400">Last updated: <?= e(get_setting('terms_updated_at', date('Y-m-d'))) ?></p>
    <div class="space-y-3 text-sm text-slate-600 leading-relaxed">
        <p><?= e($siteName) ?> collects only the account information necessary to operate your dashboard, process wallet transactions, fulfill submitted orders, and provide support.</p>
        <p>Passwords are stored exclusively using strong one-way bcrypt hashing. API keys and session tokens are transmitted strictly over secure channels and protected against CSRF and unauthorized access.</p>
        <p>Uploaded payment receipts and ticket attachments are restricted to verified administrators handling your request.</p>
    </div>
</div>

<?php include INCLUDES_PATH . '/user-footer.php'; ?>
