<?php
/**
 * =====================================================================
 * MCQG - Market Competition Quantitative Game
 * File: config/session_config.php
 * Purpose: Configures and starts the PHP session securely, once per
 *          request. Both the Admin module and the Player module load
 *          this file (via includes/auth_check.php) before any output.
 * =====================================================================
 */

if (!defined('MCQG_APP')) {
    die('Direct access not permitted.');
}

if (session_status() === PHP_SESSION_NONE) {

    // Security hardening for session cookies
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME_SECONDS,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']),  // true only when served over HTTPS
        'httponly' => true,                      // JS cannot read the cookie
        'samesite' => 'Lax',
    ]);

    session_name('MCQG_SESSION');
    session_start();

    // Idle timeout - if the last activity was too long ago, force logout
    if (isset($_SESSION['last_activity_time'])
        && (time() - $_SESSION['last_activity_time']) > SESSION_LIFETIME_SECONDS) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity_time'] = time();

    // Generate a CSRF token once per session, reused by every form
    if (empty($_SESSION[SESSION_CSRF_TOKEN])) {
        $_SESSION[SESSION_CSRF_TOKEN] = bin2hex(random_bytes(32));
    }
}
