<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$user = require_user_login();
$results = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $rawLines = trim((string)($_POST['bulk_lines'] ?? ''));
    if ($rawLines === '') {
        $error = 'Please enter at least one order line.';
    } else {
        $lines = preg_split('/\r\n|\r|\n/', $rawLines);
        if (count($lines) > 100) {
            $error = 'Maximum 100 order lines per bulk submission.';
        } else {
            foreach ($lines as $idx => $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) < 3) {
                    $results[] = ['line' => $idx + 1, 'ok' => false, 'message' => "Invalid format. Expected: service_id | link | quantity"];
                    continue;
                }
                $svcId = (int)$parts[0];
                $link  = (string)$parts[1];
                $qty   = (int)$parts[2];

                $res = Order::place((int)$user['id'], $svcId, $link, $qty);
                if ($res['ok']) {
                    $results[] = [
                        'line'    => $idx + 1,
                        'ok'      => true,
                        'message' => "Order #{$res['order_code']} placed (" . format_money($res['charge']) . ")",
                    ];
                } else {
                    $results[] = ['line' => $idx + 1, 'ok' => false, 'message' => $res['error']];
                }
            }
            $user = current_user();
        }
    }
}

$pageTitle = 'Bulk Orders';
include INCLUDES_PATH . '/user-header.php';
?>

<div class="mb-6">
    <h1 class="text-xl sm:text-2xl font-bold text-[#151515]">Bulk Order Placement</h1>
    <p class="text-sm text-slate-500 mt-0.5">Submit multiple orders at once. Each line is validated and charged atomically against your wallet.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="mb-5 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-700 font-medium"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label for="bulk_lines" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Orders (One per line: <code class="font-mono-code bg-slate-100 px-1.5 py-0.5 rounded">service_id | link | quantity</code>)
                </label>
                <textarea id="bulk_lines" name="bulk_lines" rows="10" required
                          placeholder="1 | https://instagram.com/username | 1000&#10;2 | https://youtube.com/watch?v=example | 500"
                          class="w-full p-4 rounded-xl border border-slate-200 font-mono-code text-xs leading-relaxed focus:outline-none focus:border-[#7138D8]"><?= e($_POST['bulk_lines'] ?? '') ?></textarea>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-xs text-slate-500">Available Balance: <strong class="text-[#16A36A] tabular-nums"><?= e(format_money($user['balance'])) ?></strong></span>
                <button type="submit" class="h-11 px-6 rounded-xl bg-[#7138D8] hover:bg-[#5E28C2] text-white text-sm font-semibold smooth-transition">
                    Process Bulk Orders
                </button>
            </div>
        </form>
    </div>

    <div class="lg:col-span-4 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6">
        <h2 class="text-sm font-bold text-[#151515] mb-3">Execution Results</h2>
        <?php if (empty($results)): ?>
            <p class="text-xs text-slate-500 leading-relaxed">
                Find Service IDs on the <a href="/services.php" class="text-[#7138D8] font-semibold hover:underline">Services page</a>. Each valid line deducts funds securely and creates a tracked order.
            </p>
        <?php else: ?>
            <div class="space-y-2 max-h-80 overflow-y-auto">
                <?php foreach ($results as $r): ?>
                    <div class="p-3 rounded-xl border text-xs <?= $r['ok'] ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?>">
                        <span class="font-bold">Line <?= (int)$r['line'] ?>:</span> <?= e($r['message']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include INCLUDES_PATH . '/user-footer.php'; ?>
