<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$supportEmail = get_setting('support_email', 'support@apexsmm.local');
$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $name    = clean_text($_POST['name'] ?? '', 120);
    $email   = strtolower(trim((string)($_POST['email'] ?? '')));
    $message = clean_text($_POST['message'] ?? '', 2000);

    if ($name === '' || !validate_email($email) || $message === '') {
        $error = 'Please fill in your name, a valid email address, and your message.';
    } else {
        log_activity('system', null, 'contact.submit', null, null, "From {$name} <{$email}>: " . mb_substr($message, 0, 200));
        Notification::createForAdmin(null, "Contact Inquiry from {$name}", "{$email}: " . mb_substr($message, 0, 200), 'info', '/admin/activity-logs.php');
        $sent = true;
    }
}

$pageTitle = 'Contact Us';
include INCLUDES_PATH . '/user-header.php';
?>

<div class="max-w-2xl">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515] mb-1">Contact Us</h1>
    <p class="text-sm text-slate-500 mb-6">Reach our team directly at <a href="mailto:<?= e($supportEmail) ?>" class="text-[#7138D8] font-semibold hover:underline"><?= e($supportEmail) ?></a> or send a message below.</p>

    <?php if ($sent): ?>
        <div class="mb-5 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm text-emerald-800 font-medium">
            Your message has been logged for our team. For faster order support, please open a <a href="/tickets.php" class="underline font-bold">Support Ticket</a>.
        </div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="mb-5 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-700 font-medium"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-slate-200/80 p-6">
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Your Name</label>
                <input type="text" name="name" required class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Email Address</label>
                <input type="email" name="email" required class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Message</label>
                <textarea name="message" rows="5" required class="w-full p-3.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-[#7138D8]"></textarea>
            </div>
            <button type="submit" class="h-11 px-6 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-sm font-semibold">Send Message</button>
        </form>
    </div>
</div>

<?php include INCLUDES_PATH . '/user-footer.php'; ?>
