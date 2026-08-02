<?php
/**
 * =====================================================================
 * MCQG - Market Competition Quantitative Game
 * File: config/db_connect.php
 * Purpose: The ONE file every page/module includes to get a working,
 *          ready-to-use database connection + full app bootstrap.
 *
 * Usage (top of every .php page in admin/, player/, ajax/):
 *      require_once __DIR__ . '/../../config/db_connect.php';
 *
 * This file, in order:
 *   1. Loads constants.php   (credentials, paths, business rule constants)
 *   2. Loads app_config.php  (timezone, autoloader, error handling)
 *   3. Loads session_config.php (secure session + CSRF token)
 *   4. Opens the PDO connection via core/Database.php and returns $pdo
 * =====================================================================
 */

if (!defined('MCQG_APP')) {
    define('MCQG_APP', true);
}

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/app_config.php';       // registers the autoloader, so Database.php loads automatically
require_once __DIR__ . '/session_config.php';   // starts the session for every page that includes this file

// Database.php is autoloaded by app_config.php's spl_autoload_register,
// but we still explicitly instantiate it here so every including page
// has a ready-to-use $pdo variable without repeating boilerplate.
try {
    $db  = Database::getInstance();
    $pdo = $db->getConnection();
} catch (Throwable $e) {
    Logger::error('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Unable to connect to the database. Please check config/constants.php and ensure MySQL is running in XAMPP.');
}
