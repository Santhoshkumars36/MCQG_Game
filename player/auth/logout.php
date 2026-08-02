<?php
/**
 * MCQG Player - Logout
 * Path: player/auth/logout.php
 */
require_once __DIR__ . '/../../config/app_config.php';

Auth::logout();
header('Location: ' . PLAYER_URL . 'auth/login.php');
exit;
