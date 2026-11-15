<?php
declare(strict_types=1);

function csrf_token(): string
{
    if (
        empty($_SESSION['_csrf_token']) ||
        empty($_SESSION['_csrf_time']) ||
        (time() - (int)$_SESSION['_csrf_time']) > CSRF_TOKEN_TTL
    ) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['_csrf_time']  = time();
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_token(?string $token = null): bool
{
    $submitted = $token
        ?? ($_POST['csrf_token'] ?? null)
        ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

    if (!is_string($submitted) || $submitted === '' || empty($_SESSION['_csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['_csrf_token'], $submitted);
}

function require_valid_csrf(bool $isJson = false): void
{
    if (!verify_csrf_token()) {
        if ($isJson) {
            json_response(['ok' => false, 'error' => 'Invalid or expired security token. Please refresh and try again.'], 403);
        }
        set_flash('error', 'Security token expired or invalid. Please submit the form again.');
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}
