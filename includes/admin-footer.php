<?php
declare(strict_types=1);
$siteName = get_setting('site_name', APP_NAME_DEFAULT);
?>
        </main>
        <footer class="px-6 py-4 border-t border-slate-200/80 bg-white text-xs text-slate-500 flex items-center justify-between">
            <span><?= e($siteName) ?> Administration</span>
            <span>PHP <?= e(PHP_VERSION) ?> · MySQL PDO</span>
        </footer>
    </div>
</div>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/dashboard.js"></script>
<script src="/assets/js/admin.js"></script>
</body>
</html>
