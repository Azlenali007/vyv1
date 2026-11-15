<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

$admin = require_admin_permission('announcements.manage');
$pdo   = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $title   = clean_text($_POST['title'] ?? '', 180);
        $content = clean_text($_POST['content'] ?? '', 2000);
        $type    = in_array($_POST['type'] ?? 'notice', ['update', 'promo', 'maintenance', 'notice'], true) ? $_POST['type'] : 'notice';
        if ($title !== '' && $content !== '') {
            $stmt = $pdo->prepare('INSERT INTO announcements (title, content, type, is_published, created_by_admin_id) VALUES (?, ?, ?, 1, ?)');
            $stmt->execute([$title, $content, $type, (int)$admin['id']]);
            set_flash('success', 'Announcement published.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM announcements WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        set_flash('success', 'Announcement deleted.');
    }
    redirect('/admin/announcements.php');
}

$items = $pdo->query('SELECT * FROM announcements ORDER BY id DESC')->fetchAll();
$pageTitle = 'Announcements';
include INCLUDES_PATH . '/admin-header.php';
?>

<div class="mb-6">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Announcements</h1>
    <p class="text-sm text-slate-500 mt-0.5">Publish real news, maintenance windows, and service updates visible on user dashboards.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 p-6 h-fit">
        <h2 class="text-base font-bold text-[#151515] mb-4">Publish Announcement</h2>
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Title</label>
                <input type="text" name="title" required class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Type</label>
                <select name="type" class="w-full h-11 px-3.5 rounded-xl border border-slate-200 text-sm bg-white">
                    <option value="notice">Notice</option>
                    <option value="update">Update</option>
                    <option value="promo">Promotion</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Content</label>
                <textarea name="content" rows="4" required class="w-full p-3.5 rounded-xl border border-slate-200 text-sm"></textarea>
            </div>
            <button type="submit" class="w-full h-11 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-sm font-semibold">Publish</button>
        </form>
    </div>

    <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 divide-y divide-slate-100">
        <?php if (empty($items)): ?>
            <div class="p-10 text-center text-sm text-slate-500">No announcements published yet.</div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="p-5 flex items-start justify-between gap-4">
                    <div>
                        <div class="font-bold text-sm text-[#151515]"><?= e($item['title']) ?></div>
                        <p class="text-xs text-slate-600 mt-1"><?= e($item['content']) ?></p>
                        <div class="text-[11px] text-slate-400 mt-1.5"><?= e(ucfirst($item['type'])) ?> · <?= e(date('d M Y, H:i', strtotime($item['created_at']))) ?></div>
                    </div>
                    <form method="post" onsubmit="return confirm('Delete announcement?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:underline">Delete</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include INCLUDES_PATH . '/admin-footer.php'; ?>
