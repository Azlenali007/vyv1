<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function json_response(array $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash_messages'][] = [
        'type'    => $type,
        'message' => $message,
    ];
}

function get_flashes(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return is_array($messages) ? $messages : [];
}

function get_all_settings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
        $cache = [];
        foreach ($stmt->fetchAll() as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
        return $cache;
    } catch (Throwable) {
        return [];
    }
}

function get_setting(string $key, string $default = ''): string
{
    $settings = get_all_settings();
    return $settings[$key] ?? $default;
}

function set_setting(string $key, string $value, string $group = 'general'): void
{
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_group = VALUES(setting_group)'
    );
    $stmt->execute([$key, $value, $group]);
}

function format_money(float|string|int $amount, int $decimals = 2): string
{
    $symbol = get_setting('currency_symbol', DEFAULT_CURRENCY_SYMBOL);
    $num = (float)$amount;
    if ($num < 0) {
        return '-' . $symbol . number_format(abs($num), $decimals);
    }
    return $symbol . number_format($num, $decimals);
}

function format_rate(float|string|int $rate): string
{
    return format_money((float)$rate, 2);
}

function generate_code(string $prefix, int $bytes = 5): string
{
    return $prefix . strtoupper(bin2hex(random_bytes($bytes)));
}

function client_ip(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function user_agent(): string
{
    return mb_substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 250);
}

function log_activity(string $actorType, ?int $actorId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void
{
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (actor_type, actor_id, action, entity_type, entity_id, details, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $actorType,
            $actorId,
            $action,
            $entityType,
            $entityId,
            $details,
            mb_substr(client_ip(), 0, 45),
            user_agent(),
        ]);
    } catch (Throwable $e) {
        error_log('Activity log failed: ' . $e->getMessage());
    }
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return max(1, $diff) . 's ago';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . 'd ago';
    }
    return date('d M Y', $ts);
}

function handle_secure_upload(array $file, string $destDir, array $allowedMimes): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'File upload failed or no file selected.'];
    }
    if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
        return ['ok' => false, 'error' => 'File exceeds maximum 5 MB size limit.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Invalid upload source.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMimes, true)) {
        return ['ok' => false, 'error' => 'Unsupported file format (' . $mime . ').'];
    }

    $extMap = [
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
        'image/webp'       => 'webp',
        'image/gif'        => 'gif',
        'application/pdf'  => 'pdf',
        'text/plain'       => 'txt',
    ];
    $ext = $extMap[$mime] ?? 'bin';
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;

    if (!is_dir($destDir)) {
        mkdir($destDir, 0750, true);
    }

    $target = rtrim($destDir, '/') . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return ['ok' => false, 'error' => 'Unable to store uploaded file.'];
    }

    return ['ok' => true, 'filename' => $filename];
}

function order_status_meta(string $status): array
{
    return match ($status) {
        'completed'   => ['label' => 'Completed',   'bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => '#16A36A'],
        'processing'  => ['label' => 'Processing',  'bg' => 'bg-amber-50 text-amber-700 border-amber-200',       'dot' => '#D97706'],
        'in_progress' => ['label' => 'In Progress', 'bg' => 'bg-purple-50 text-purple-700 border-purple-200',   'dot' => '#7138D8'],
        'pending'     => ['label' => 'Pending',     'bg' => 'bg-violet-50 text-violet-700 border-violet-200',   'dot' => '#8B5CF6'],
        'partial'     => ['label' => 'Partial',     'bg' => 'bg-sky-50 text-sky-700 border-sky-200',            'dot' => '#0284C7'],
        'cancelled'   => ['label' => 'Cancelled',   'bg' => 'bg-rose-50 text-rose-700 border-rose-200',         'dot' => '#DC2626'],
        'refunded'    => ['label' => 'Refunded',    'bg' => 'bg-slate-100 text-slate-700 border-slate-200',     'dot' => '#475569'],
        'failed'      => ['label' => 'Failed',      'bg' => 'bg-red-50 text-red-700 border-red-200',            'dot' => '#B91C1C'],
        default       => ['label' => ucfirst($status), 'bg' => 'bg-slate-100 text-slate-700 border-slate-200',  'dot' => '#64748B'],
    };
}

function platform_icon_svg(string $platform): string
{
    $p = strtolower(trim($platform));
    if (str_contains($p, 'instagram')) {
        return '<span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-tr from-amber-500 via-pink-500 to-purple-600 text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg></span>';
    }
    if (str_contains($p, 'youtube')) {
        return '<span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-600 text-white"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M21.6 7.2a2.7 2.7 0 0 0-1.9-1.9C18 4.8 12 4.8 12 4.8s-6 0-7.7.5a2.7 2.7 0 0 0-1.9 1.9C2 8.9 2 12 2 12s0 3.1.4 4.8a2.7 2.7 0 0 0 1.9 1.9c1.7.5 7.7.5 7.7.5s6 0 7.7-.5a2.7 2.7 0 0 0 1.9-1.9c.4-1.7.4-4.8.4-4.8s0-3.1-.4-4.8zM10 15V9l5.2 3-5.2 3z"/></svg></span>';
    }
    if (str_contains($p, 'tiktok')) {
        return '<span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#151515] text-white"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M16.5 3a5.5 5.5 0 0 0 4 4.8V11a8.6 8.6 0 0 1-4-1.1V15a6 6 0 1 1-5.2-5.9v3.2a2.8 2.8 0 1 0 2 2.7V3h3.2z"/></svg></span>';
    }
    if (str_contains($p, 'facebook')) {
        return '<span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M14 13.5h2.5l1-4H14v-2c0-1 .5-2 2-2h1.5V2.1C17 2 15.8 2 14.5 2 11.8 2 10 3.7 10 6.7V9.5H7v4h3V22h4v-8.5z"/></svg></span>';
    }
    if (str_contains($p, 'telegram')) {
        return '<span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-500 text-white"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M21.5 3.8 2.6 11.1c-1.3.5-1.3 1.3-.2 1.6l4.8 1.5 11.2-7.1c.5-.3 1-.1.6.2l-9.1 8.2-.3 4.8c.5 0 .7-.2 1-.5l2.3-2.2 4.8 3.5c.9.5 1.5.2 1.7-.8l3.1-14.6c.3-1.3-.5-1.9-1-1.9z"/></svg></span>';
    }
    if (str_contains($p, 'twitter') || str_contains($p, 'x')) {
        return '<span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-neutral-900 text-white"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.2 3h3.3l-7.2 8.2L22.8 21h-6.6l-5.2-6.8L5.1 21H1.8l7.7-8.8L1.4 3h6.8l4.7 6.2L18.2 3z"/></svg></span>';
    }
    return '<span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#7138D8]/10 text-[#7138D8]"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></span>';
}
