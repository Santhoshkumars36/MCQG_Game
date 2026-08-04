<?php
/**
 * MCQG - Root Logout
 * Path: logout.php
 * A convenience entry point that logs out whichever role is
 * currently signed in (admin or team) and sends them back to the
 * correct login screen. admin/auth/logout.php and
 * player/auth/logout.php remain the role-specific versions used by
 * the in-app sidebar links; this one is for a generic top-level link.
 */
require_once __DIR__ . '/config/app_config.php';

$wasAdmin = Auth::isAdmin();
$wasTeam = Auth::isTeam();

Auth::logout();

if ($wasAdmin) {
    header('Location: ' . ADMIN_URL . 'auth/login.php');
} elseif ($wasTeam) {
    header('Location: ' . PLAYER_URL . 'auth/login.php');
} else {
    header('Location: ' . BASE_URL . 'index.php');
}
exit;
