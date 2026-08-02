<?php
/**
 * MCQG Admin - Logout
 * Path: admin/auth/logout.php
 */
require_once __DIR__ . '/../../config/app_config.php';

Auth::logout();
header('Location: ' . ADMIN_URL . 'auth/login.php');
exit;
