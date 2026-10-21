<?php
declare(strict_types=1);

/**
 * SmartCanteen AI - Authentication & RBAC
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function is_logged_in(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['role_name']);
}

function current_user_id(): ?int
{
    return is_logged_in() ? (int) $_SESSION['user_id'] : null;
}

function current_role(): ?string
{
    return is_logged_in() ? (string) $_SESSION['role_name'] : null;
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    static $user = false;

    if ($user !== false) {
        return $user;
    }

    $stmt = db()->prepare(
        'SELECT u.user_id, u.full_name, u.email, u.phone, u.avatar,
                u.is_active, r.role_name
         FROM users u
         INNER JOIN roles r ON r.role_id = u.role_id
         WHERE u.user_id = ?
         LIMIT 1'
    );
    $stmt->execute([current_user_id()]);
    $user = $stmt->fetch();

    if (!$user || !(int) $user['is_active']) {
        logout_user();
        return null;
    }

    return $user;
}

function require_login(): void
{
    if (!is_logged_in() || current_user() === null) {
        set_flash('error', 'Please log in to continue.');
        redirect('/SmartCanteenAI/auth/login.php');
    }
}

function require_role(string ...$allowed_roles): void
{
    require_login();

    $role = current_role();

    if ($role === null || !in_array($role, $allowed_roles, true)) {
        http_response_code(403);
        exit('403 Forbidden — You do not have permission to access this page.');
    }
}

function role_dashboard(string $role): string
{
    return match ($role) {
        'STUDENT' => '/SmartCanteenAI/student/student_dashboard.php',
        'STAFF' => '/SmartCanteenAI/staff/staff_dashboard.php',
        'ADMIN' => '/SmartCanteenAI/admin/admin_dashboard.php',
        'MANAGER' => '/SmartCanteenAI/manager/manager_dashboard.php',
        default => '/SmartCanteenAI/index.php',
    };
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function login_user(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['role_name'] = (string) $user['role_name'];
    $_SESSION['logged_in_at'] = time();
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'] ?? '',
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function get_flash(string $type): ?string
{
    $message = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $message;
}
