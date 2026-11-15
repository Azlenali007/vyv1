<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$siteName = get_setting('site_name', APP_NAME_DEFAULT);
$tagline  = get_setting('site_tagline', 'Automated Social Media Marketing Infrastructure');

$pdo = Database::getConnection();
$activeServicesCount = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status = 'active'")->fetchColumn();
$activeCategories    = Category::all(true);
$sampleServices      = $pdo->query(
    "SELECT s.id, s.name, s.rate_per_1000, s.min_qty, s.max_qty, c.name AS category_name, c.platform
     FROM services s
     INNER JOIN categories c ON c.id = s.category_id
     WHERE s.status = 'active' AND c.status = 'active'
     ORDER BY s.sort_order ASC, s.id ASC LIMIT 8"
)->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($siteName) ?> · <?= e($tagline) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-white text-[#151515] antialiased">
    <!-- Clean 3-Zone Top Bar -->
    <header class="border-b border-slate-200/80 bg-white sticky top-0 z-30">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-8">
            <a href="/index.php" class="flex items-center gap-2.5 whitespace-nowrap shrink-0">
                <span class="w-8 h-8 rounded-xl bg-[#7138D8] flex items-center justify-center text-white">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm0 2h14v2H5v-2z"/></svg>
                </span>
                <span class="text-lg font-bold tracking-tight text-[#151515]"><?= e($siteName) ?></span>
            </a>

            <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-slate-600">
                <a href="/services.php" class="hover:text-[#151515] whitespace-nowrap shrink-0">Services</a>
                <a href="/faq.php" class="hover:text-[#151515] whitespace-nowrap shrink-0">FAQ</a>
                <a href="/contact.php" class="hover:text-[#151515] whitespace-nowrap shrink-0">Contact</a>
                <a href="/login.php" class="hover:text-[#151515] whitespace-nowrap shrink-0">Sign In</a>
            </nav>

            <div class="flex items-center gap-3 shrink-0">
                <a href="/register.php" class="px-4 py-2 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-xs font-semibold whitespace-nowrap shrink-0 smooth-transition">
                    Get Started
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="py-16 sm:py-24 px-4 sm:px-6 bg-gradient-to-b from-[#F8F6FF] to-white border-b border-slate-100">
        <div class="max-w-6xl mx-auto grid lg:grid-cols-12 gap-10 items-center">
            <div class="lg:col-span-7">
                <h1 class="text-4xl sm:text-5xl font-bold tracking-tight text-[#151515] leading-[1.12] [text-wrap:balance]">
                    Automated Social Media Fulfillment Built for Agencies & Resellers
                </h1>
                <p class="mt-5 text-base sm:text-lg text-slate-600 max-w-2xl leading-relaxed">
                    <?= e($tagline) ?>. Place single or bulk campaigns, track live provider status updates, manage wallet deposits, and connect directly over REST API.
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="/register.php" class="px-6 py-3 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-sm font-semibold smooth-transition">
                        Create Free Account
                    </a>
                    <a href="/login.php" class="px-6 py-3 rounded-xl bg-[#151515] hover:bg-neutral-800 text-white text-sm font-semibold smooth-transition">
                        Sign In to Panel
                    </a>
                    <a href="/services.php" class="px-5 py-3 rounded-xl border border-slate-200 hover:border-slate-300 text-slate-700 text-sm font-semibold smooth-transition">
                        Browse Catalog (<?= $activeServicesCount ?>)
                    </a>
                </div>
            </div>

            <div class="lg:col-span-5">
                <div class="hero-banner-gradient rounded-3xl p-6 sm:p-8 text-white shadow-2xl shadow-[#7138D8]/15">
                    <div class="text-xs font-medium text-purple-200">Platform Architecture</div>
                    <div class="mt-2 text-2xl font-bold">Real-Time Order & Wallet Engine</div>
                    <div class="mt-6 space-y-4 text-sm">
                        <div class="flex items-start justify-between border-b border-white/10 pb-3">
                            <span class="text-purple-100">Active Catalog Services</span>
                            <span class="font-bold tabular-nums"><?= number_format($activeServicesCount) ?></span>
                        </div>
                        <div class="flex items-start justify-between border-b border-white/10 pb-3">
                            <span class="text-purple-100">Active Categories</span>
                            <span class="font-bold tabular-nums"><?= number_format(count($activeCategories)) ?></span>
                        </div>
                        <div class="flex items-start justify-between">
                            <span class="text-purple-100">API Integration</span>
                            <span class="font-semibold text-[#16A36A]">JSON REST v2</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Live Services Preview -->
    <section class="py-16 px-4 sm:px-6 max-w-6xl mx-auto">
        <div class="flex items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-[#151515]">Active Services</h2>
                <p class="text-sm text-slate-500 mt-1">Live pricing directly from the panel database.</p>
            </div>
            <a href="/services.php" class="text-sm font-semibold text-[#7138D8] hover:underline whitespace-nowrap">View All Services &rarr;</a>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <?php if (empty($sampleServices)): ?>
                <div class="p-10 text-center">
                    <div class="text-base font-semibold text-[#151515]">No Services Published Yet</div>
                    <p class="text-sm text-slate-500 mt-1">An administrator can add or import services from connected API providers in the Admin Panel.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold text-slate-500">
                                <th class="px-5 py-3.5">ID</th>
                                <th class="px-5 py-3.5">Service</th>
                                <th class="px-5 py-3.5">Category</th>
                                <th class="px-5 py-3.5 text-right">Rate / 1000</th>
                                <th class="px-5 py-3.5 text-right">Limits</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php foreach ($sampleServices as $svc): ?>
                                <tr class="table-row-hover">
                                    <td class="px-5 py-3.5 font-mono-code text-xs text-slate-500">#<?= (int)$svc['id'] ?></td>
                                    <td class="px-5 py-3.5 font-medium text-[#151515]"><?= e($svc['name']) ?></td>
                                    <td class="px-5 py-3.5 text-slate-600"><?= e($svc['category_name']) ?></td>
                                    <td class="px-5 py-3.5 text-right font-semibold text-[#151515] tabular-nums"><?= e(format_rate($svc['rate_per_1000'])) ?></td>
                                    <td class="px-5 py-3.5 text-right text-xs text-slate-500 tabular-nums"><?= number_format((int)$svc['min_qty']) ?> – <?= number_format((int)$svc['max_qty']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <footer class="border-t border-slate-200 py-8 px-4 sm:px-6 text-xs text-slate-500">
        <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div>&copy; <?= date('Y') ?> <?= e($siteName) ?>.</div>
            <div class="flex items-center gap-6">
                <a href="/terms.php" class="hover:text-[#151515]">Terms</a>
                <a href="/privacy.php" class="hover:text-[#151515]">Privacy</a>
                <a href="/faq.php" class="hover:text-[#151515]">FAQ</a>
                <a href="/contact.php" class="hover:text-[#151515]">Contact</a>
                <a href="/admin/login.php" class="hover:text-[#151515]">Admin</a>
            </div>
        </div>
    </footer>
</body>
</html>
