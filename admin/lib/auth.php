<?php
/**
 * admin/lib/auth.php — minimal single-admin session auth.
 * No user table needed — the site has one operator (you). The password
 * hash lives in an env var, same trust tier as the OpenRouter key.
 */

const ADMIN_SESSION_KEY = 'vp_admin_authed';
const ADMIN_SESSION_TTL_SECONDS = 3600 * 8; // 8 hours, then re-login

function admin_boot_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/admin',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

/**
 * Call at the top of every admin page except login.php.
 * Redirects to login if not authenticated or session expired.
 */
function require_admin(): void {
    admin_boot_session();

    $authed = $_SESSION[ADMIN_SESSION_KEY] ?? false;
    $loginTime = $_SESSION['vp_admin_login_at'] ?? 0;
    $expired = (time() - $loginTime) > ADMIN_SESSION_TTL_SECONDS;

    if (!$authed || $expired) {
        session_unset();
        header('Location: /admin/login.php');
        exit;
    }
}

/**
 * Verifies the submitted password against the env-stored hash.
 * Generate the hash once locally with: php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"
 * and set it as ADMIN_PASSWORD_HASH in Coolify — never store the plaintext password anywhere.
 */
function attempt_admin_login(string $password): bool {
    $hash = getenv('ADMIN_PASSWORD_HASH');
    if (!$hash) {
        throw new RuntimeException('ADMIN_PASSWORD_HASH is not configured');
    }

    if (password_verify($password, $hash)) {
        admin_boot_session();
        session_regenerate_id(true); // prevent session fixation
        $_SESSION[ADMIN_SESSION_KEY] = true;
        $_SESSION['vp_admin_login_at'] = time();
        return true;
    }

    // Deliberate small delay on failure — cheap brute-force friction.
    usleep(400000);
    return false;
}

function admin_logout(): void {
    admin_boot_session();
    session_unset();
    session_destroy();
}
