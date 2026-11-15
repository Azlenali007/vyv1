<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('backup.manage');
$pdo   = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'download_backup') {
    require_valid_csrf();
    log_activity('admin', (int)$admin['id'], 'backup.download', 'database', null, 'Generated full MySQL SQL backup');

    $filename = 'smm_panel_backup_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "-- ApexSMM Panel MySQL Backup\n";
    echo "-- Generated: " . date('c') . "\n\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
        echo "DROP TABLE IF EXISTS `{$table}`;\n";
        echo ($create['Create Table'] ?? '') . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll();
        foreach ($rows as $row) {
            $cols = array_map(fn($c) => "`{$c}`", array_keys($row));
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
            echo "INSERT INTO `{$table}` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
        }
        echo "\n";
    }
    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    exit;
}

$tableStats = [];
foreach ($pdo->query('SHOW TABLE STATUS')->fetchAll() as $st) {
    $tableStats[] = [
        'name' => $st['Name'],
        'rows' => (int)($st['Rows'] ?? 0),
        'size' => (int)(($st['Data_length'] ?? 0) + ($st['Index_length'] ?? 0)),
    ];
}

$pageTitle = 'Database Backup';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Database Backup</h1>
        <p class="text-sm text-slate-500 mt-0.5">Export a complete SQL snapshot of all 20 MySQL tables directly via PDO.</p>
    </div>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="download_backup">
        <button type="submit" class="h-10 px-5 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-xs font-semibold">Download .SQL Backup</button>
    </form>
</div>

<div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
    <table class="w-full text-left border-collapse text-sm">
        <thead>
            <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold text-slate-500">
                <th class="px-4 py-3.5">Table Name</th>
                <th class="px-4 py-3.5 text-right">Approx. Rows</th>
                <th class="px-4 py-3.5 text-right">Storage Size</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($tableStats as $t): ?>
                <tr>
                    <td class="px-4 py-3 font-mono-code text-xs font-semibold text-[#151515]"><?= e($t['name']) ?></td>
                    <td class="px-4 py-3 text-right tabular-nums text-xs"><?= number_format($t['rows']) ?></td>
                    <td class="px-4 py-3 text-right tabular-nums text-xs text-slate-500"><?= number_format($t['size'] / 1024, 1) ?> KB</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
