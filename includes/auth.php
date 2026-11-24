<?php
/**
 * includes/auth.php — sesi aman, login/logout, dan guard peran pengguna.
 */

/** Mulai sesi dengan pengaturan yang lebih aman. */
function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/** Apakah pengguna saat ini sudah login. */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/** Data pengguna yang sedang login (atau null). */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Wajibkan login; jika belum, arahkan ke halaman login dengan URL kembali. */
function require_login(): void
{
    if (!is_logged_in()) {
        $kembali = $_SERVER['REQUEST_URI'] ?? '';
        set_flash('warning', 'Silakan masuk terlebih dahulu untuk melanjutkan booking.');
        redirect(BASE_URL . '/login.php?kembali=' . urlencode($kembali));
    }
}

/** Wajibkan salah satu peran yang diizinkan. */
function require_role(string ...$roles): void
{
    require_login();
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles, true)) {
        set_flash('danger', 'Anda tidak memiliki hak akses ke halaman tersebut.');
        redirect(BASE_URL . '/index.php');
    }
}

/** Proses login: verifikasi email + password, lalu regenerasi ID sesi. */
function attempt_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    session_regenerate_id(true);
    unset($user['password']);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = $user;
    return true;
}

/** Keluar dari akun saat ini. */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
