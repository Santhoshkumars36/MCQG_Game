<?php
/**
 * =====================================================================
 * MCQG - Market Competition Quantitative Game
 * File: config/app_config.php
 * Purpose: Application-wide settings (timezone, error display, autoload
 *          of core classes) - loaded once per request, right after
 *          constants.php.
 * =====================================================================
 */

if (!defined('MCQG_APP')) {
    die('Direct access not permitted.');
}

// ---------------------------------------------------------------------
// ENVIRONMENT
// ---------------------------------------------------------------------
define('APP_ENV', 'development'); // 'development' or 'production'

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

date_default_timezone_set('Asia/Kolkata');

// ---------------------------------------------------------------------
// APPLICATION META
// ---------------------------------------------------------------------
define('APP_NAME', 'MCQG - Market Competition Quantitative Game');
define('APP_VERSION', '1.0.0');
define('DEFAULT_CURRENCY_SYMBOL', '₹');

// ---------------------------------------------------------------------
// AUTOLOAD CORE CLASSES + REUSABLE MODELS
// (removes the need to manually require every class file everywhere)
// ---------------------------------------------------------------------
spl_autoload_register(function ($className) {
    $searchPaths = [
        ROOT_PATH . '/core/',
        ROOT_PATH . '/classes/',
    ];
    foreach ($searchPaths as $path) {
        $file = $path . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// ---------------------------------------------------------------------
// GLOBAL ERROR / EXCEPTION HANDLING -> routed to core/Logger.php
// ---------------------------------------------------------------------
set_exception_handler(function (Throwable $e) {
    Logger::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (APP_ENV === 'development') {
        echo '<pre style="background:#2b2b2b;color:#ff8a80;padding:16px;border-radius:8px;">'
            . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString())
            . '</pre>';
    } else {
        echo 'Something went wrong. Please try again or contact the administrator.';
    }
});

set_error_handler(function ($severity, $message, $file, $line) {
    Logger::error("$message in $file:$line");
    return false; // continue with PHP's normal error handler too
});
