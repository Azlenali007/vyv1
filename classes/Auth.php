<?php
declare(strict_types=1);

class Auth
{
    public static function registerUser(string $username, string $fullName, string $email, string $password): array
    {
        $pdo = Database::getConnection();

        $username = trim($username);
        $fullName = clean_text($fullName, 120);
        $email    = strtolower(trim($email));

        if (!validate_username($username)) {
            return ['ok' => false, 'error' => 'Username must be 3–40 characters (letters, numbers, underscore only).'];
        }
        if ($fullName === '' || mb_strlen($fullName) < 2) {
            return ['ok' => false, 'error' => 'Please enter your full name.'];
        }
        if (!validate_email($email)) {
            return ['ok' => false, 'error' => 'Please enter a valid email address.'];
        }
        if ($pwdErr = validate_password($password)) {
            return ['ok' => false, 'error' => $pwdErr];
        }

        $check = $pdo->prepare('SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1');
        $check->execute([$username, $email]);
        if ($existing = $check->fetch()) {
            if (strcasecmp($existing['username'], $username) === 0) {
                return ['ok' => false, 'error' => 'That username is already taken.'];
            }
            return ['ok' => false, 'error' => 'An account with this email already exists.'];
        }

        $hash   = password_hash($password, PASSWORD_BCRYPT);
        $apiKey = bin2hex(random_bytes(24));

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, full_name, email, password_hash, balance, api_key, status)
             VALUES (?, ?, ?, ?, 0.0000, ?, "active")'
        );
        $stmt->execute([$username, $fullName, $email, $hash, $apiKey]);
        $userId = (int)$pdo->lastInsertId();

        Notification::createForUser(
            $userId,
            'Welcome to ' . get_setting('site_name', APP_NAME_DEFAULT),
            'Your account is ready. Add funds to start placing social media marketing orders.',
            'success',
            '/add-funds.php'
        );

        log_activity('user', $userId, 'user.register', 'user', $userId, 'Registered new account');

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $userId;

        return ['ok' => true, 'user_id' => $userId];
    }

    public static function loginUser(string $identifier, string $password): array
    {
        $pdo = Database::getConnection();
        $identifier = trim($identifier);

        if ($identifier === '' || $password === '') {
            return ['ok' => false, 'error' => 'Please enter your email/username and password.'];
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([strtolower($identifier), $identifier]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['ok' => false, 'error' => 'Invalid login credentials.'];
        }

        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $mins = (int)ceil((strtotime($user['locked_until']) - time()) / 60);
            return ['ok' => false, 'error' => "Account temporarily locked due to failed attempts. Try again in {$mins} minute(s)."];
        }

        if ($user['status'] !== 'active') {
            return ['ok' => false, 'error' => 'Your account is ' . $user['status'] . '. Contact support for assistance.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            $attempts = (int)$user['failed_login_attempts'] + 1;
            $lockedUntil = $attempts >= MAX_LOGIN_ATTEMPTS ? date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_SECONDS) : null;
            $upd = $pdo->prepare('UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?');
            $upd->execute([$attempts, $lockedUntil, $user['id']]);
            return ['ok' => false, 'error' => 'Invalid login credentials.'];
        }

        $upd = $pdo->prepare(
            'UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ? WHERE id = ?'
        );
        $upd->execute([mb_substr(client_ip(), 0, 45), $user['id']]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = (int)$user['id'];

        log_activity('user', (int)$user['id'], 'user.login', 'user', (int)$user['id'], 'Signed in');

        return ['ok' => true, 'user' => $user];
    }

    public static function loginAdmin(string $identifier, string $password): array
    {
        $pdo = Database::getConnection();
        $identifier = trim($identifier);

        if ($identifier === '' || $password === '') {
            return ['ok' => false, 'error' => 'Please enter admin username/email and password.'];
        }

        $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([strtolower($identifier), $identifier]);
        $admin = $stmt->fetch();

        if (!$admin) {
            return ['ok' => false, 'error' => 'Invalid administrator credentials.'];
        }

        if ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
            $mins = (int)ceil((strtotime($admin['locked_until']) - time()) / 60);
            return ['ok' => false, 'error' => "Admin account temporarily locked. Try again in {$mins} minute(s)."];
        }

        if ($admin['status'] !== 'active') {
            return ['ok' => false, 'error' => 'This administrator account is inactive.'];
        }

        if (!password_verify($password, $admin['password_hash'])) {
            $attempts = (int)$admin['failed_login_attempts'] + 1;
            $lockedUntil = $attempts >= MAX_LOGIN_ATTEMPTS ? date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_SECONDS) : null;
            $upd = $pdo->prepare('UPDATE admins SET failed_login_attempts = ?, locked_until = ? WHERE id = ?');
            $upd->execute([$attempts, $lockedUntil, $admin['id']]);
            return ['ok' => false, 'error' => 'Invalid administrator credentials.'];
        }

        $upd = $pdo->prepare(
            'UPDATE admins SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ? WHERE id = ?'
        );
        $upd->execute([mb_substr(client_ip(), 0, 45), $admin['id']]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['admin_id'] = (int)$admin['id'];

        log_activity('admin', (int)$admin['id'], 'admin.login', 'admin', (int)$admin['id'], 'Admin signed in');

        return ['ok' => true, 'admin' => $admin];
    }

    public static function logoutUser(): void
    {
        if (!empty($_SESSION['user_id'])) {
            log_activity('user', (int)$_SESSION['user_id'], 'user.logout', 'user', (int)$_SESSION['user_id'], 'Signed out');
        }
        unset($_SESSION['user_id']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function logoutAdmin(): void
    {
        if (!empty($_SESSION['admin_id'])) {
            log_activity('admin', (int)$_SESSION['admin_id'], 'admin.logout', 'admin', (int)$_SESSION['admin_id'], 'Admin signed out');
        }
        unset($_SESSION['admin_id']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
