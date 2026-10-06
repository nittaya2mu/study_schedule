<?php
/**
 * Bootstrap — โหลดทุกหน้า/ทุก API: session, database, helper functions, authentication, CSRF
 */
require_once __DIR__ . '/../config/database.php';

const APP_NAME = 'Smart Study Scheduler';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/** Escape HTML output */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------
function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $id = current_user_id();
        $user = null;
        if ($id) {
            $st = db()->prepare('SELECT user_id, name, email, daily_target_hours, daily_max_hours, session_minutes,
                                        break_minutes, max_consecutive, created_at FROM users WHERE user_id = ?');
            $st->execute([$id]);
            $user = $st->fetch() ?: null;
        }
    }
    return $user;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** ใช้ในหน้าเว็บ: ถ้ายังไม่ login ให้ไปหน้า login */
function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

// ---------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && hash_equals(csrf_token(), $token);
}

// ---------------------------------------------------------------------
// Flash messages (สำหรับฟอร์มแบบ POST ปกติ)
// ---------------------------------------------------------------------
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------------------------------------------------------------------
// Date helpers
// ---------------------------------------------------------------------
function today(): string
{
    return date('Y-m-d');
}

function days_between(string $from, string $to): int
{
    return (int)round((strtotime($to . ' 12:00') - strtotime($from . ' 12:00')) / 86400);
}

const DAY_NAMES = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
const DAY_NAMES_TH = [1 => 'จันทร์', 2 => 'อังคาร', 3 => 'พุธ', 4 => 'พฤหัสบดี', 5 => 'ศุกร์', 6 => 'เสาร์', 7 => 'อาทิตย์'];
