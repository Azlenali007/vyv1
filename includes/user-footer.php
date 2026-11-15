<?php
declare(strict_types=1);

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
$siteName    = get_setting('site_name', APP_NAME_DEFAULT);
?>
        </main>

        <!-- Clean Desktop Footer -->
        <footer class="hidden lg:flex items-center justify-between px-6 py-4 border-t border-slate-200/80 bg-white text-xs text-slate-500">
            <div>&copy; <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</div>
            <div class="flex items-center gap-5">
                <a href="/terms.php" class="hover:text-[#151515]">Terms</a>
                <a href="/privacy.php" class="hover:text-[#151515]">Privacy</a>
                <a href="/faq.php" class="hover:text-[#151515]">FAQ</a>
                <a href="/contact.php" class="hover:text-[#151515]">Contact</a>
            </div>
        </footer>
    </div>
</div>

<!-- Mobile Bottom Navigation Bar (Compact <= 15% viewport height) -->
<?php if (current_user()): ?>
<nav class="lg:hidden fixed bottom-0 inset-x-0 z-30 h-16 bg-white border-t border-slate-200 grid grid-cols-5 items-center px-1">
    <a href="/dashboard.php" class="flex flex-col items-center justify-center gap-1 text-[11px] font-medium <?= $currentPage === 'dashboard.php' ? 'text-[#7138D8]' : 'text-slate-500' ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>
        <span>Home</span>
    </a>
    <a href="/orders.php" class="flex flex-col items-center justify-center gap-1 text-[11px] font-medium <?= in_array($currentPage, ['orders.php', 'order-details.php'], true) ? 'text-[#7138D8]' : 'text-slate-500' ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
        <span>Orders</span>
    </a>
    <a href="/add-funds.php" class="flex flex-col items-center justify-center gap-1 text-[11px] font-medium <?= $currentPage === 'add-funds.php' ? 'text-[#7138D8]' : 'text-slate-500' ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        <span>Wallet</span>
    </a>
    <a href="/tickets.php" class="flex flex-col items-center justify-center gap-1 text-[11px] font-medium <?= in_array($currentPage, ['tickets.php', 'ticket-details.php'], true) ? 'text-[#7138D8]' : 'text-slate-500' ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        <span>Support</span>
    </a>
    <a href="/profile.php" class="flex flex-col items-center justify-center gap-1 text-[11px] font-medium <?= $currentPage === 'profile.php' ? 'text-[#7138D8]' : 'text-slate-500' ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        <span>Profile</span>
    </a>
</nav>
<?php endif; ?>

<script src="/assets/js/app.js"></script>
<script src="/assets/js/dashboard.js"></script>
<script src="/assets/js/orders.js"></script>
<script src="/assets/js/services.js"></script>
</body>
</html>
