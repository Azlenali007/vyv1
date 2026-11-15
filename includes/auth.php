<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cachedUser = null;
    static $cachedId = null;

    $uid = (int)$_SESSION['user_id'];
    if ($cachedUser !== null && $cachedId === $uid) {
        return $cachedUser;
    }

    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$uid]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user_id']);
        return null;
    }

    $cachedUser = $user;
    $cachedId = $uid;
    return $user;
}

function require_user_login(bool $isApi = false): array
{
    $user = current_user();
    if (!$user) {
        if ($isApi) {
            json_response(['ok' => false, 'error' => 'Authentication required.'], 401);
        }
        set_flash('error', 'Please sign in to access your dashboard.');
        redirect('/login.php');
    }
    return $user;
}

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    static $cachedAdmin = null;
    static $cachedId = null;

    $aid = (int)$_SESSION['admin_id'];
    if ($cachedAdmin !== null && $cachedId === $aid) {
        return $cachedAdmin;
    }

    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        'SELECT a.*, r.name AS role_name, r.slug AS role_slug
         FROM admins a
         INNER JOIN admin_roles r ON r.id = a.role_id
         WHERE a.id = ? LIMIT 1'
    );
    $stmt->execute([$aid]);
    $admin = $stmt->fetch();

    if (!$admin || $admin['status'] !== 'active') {
        unset($_SESSION['admin_id']);
        return null;
    }

    $cachedAdmin = $admin;
    $cachedId = $aid;
    return $admin;
}

function require_admin_login(bool $isApi = false): array
{
    $admin = current_admin();
    if (!$admin) {
        if ($isApi) {
            json_response(['ok' => false, 'error' => 'Admin authentication required.'], 401);
        }
        redirect('/admin/login.php');
    }
    return $admin;
}

function admin_has_permission(array $admin, string $permissionKey): bool
{
    if (($admin['role_slug'] ?? '') === 'super_admin') {
        return true;
    }
    static $permCache = [];
    $roleId = (int)$admin['role_id'];
    if (!isset($permCache[$roleId])) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT permission_key, allowed FROM admin_permissions WHERE role_id = ?');
        $stmt->execute([$roleId]);
        $permCache[$roleId] = [];
        foreach ($stmt->fetchAll() as $row) {
            $permCache[$roleId][$row['permission_key']] = (bool)$row['allowed'];
        }
    }
    return $permCache[$roleId][$permissionKey] ?? false;
}

function require_admin_permission(string $permissionKey, bool $isApi = false): array
{
    $admin = require_admin_login($isApi);
    if (!admin_has_permission($admin, $permissionKey)) {
        if ($isApi) {
            json_response(['ok' => false, 'error' => 'Insufficient permissions for this action.'], 403);
        }
        set_flash('error', 'You do not have permission to access that section.');
        redirect('/admin/dashboard.php');
    }
    return $admin;
}
