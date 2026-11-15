<?php
declare(strict_types=1);

function validate_email(string $email): bool
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

function validate_username(string $username): bool
{
    return (bool)preg_match('/^[a-zA-Z0-9_]{3,40}$/', trim($username));
}

function validate_password(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must include both letters and numbers.';
    }
    return null;
}

function validate_url_or_target(string $link): bool
{
    $link = trim($link);
    if ($link === '' || strlen($link) > 1000) {
        return false;
    }
    // Accept http(s) URLs or @username social handles
    if (filter_var($link, FILTER_VALIDATE_URL)) {
        $scheme = parse_url($link, PHP_URL_SCHEME);
        return in_array(strtolower((string)$scheme), ['http', 'https'], true);
    }
    return (bool)preg_match('/^@?[a-zA-Z0-9._-]{2,120}$/', $link);
}

function clean_text(?string $text, int $maxLength = 500): string
{
    $text = trim((string)$text);
    if (mb_strlen($text) > $maxLength) {
        $text = mb_substr($text, 0, $maxLength);
    }
    return $text;
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'category-' . bin2hex(random_bytes(3));
}
