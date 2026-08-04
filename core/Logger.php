<?php
/**
 * =====================================================================
 * MCQG - core/Logger.php
 * Purpose: Writes to the two log files defined in the locked folder
 *          structure:
 *            logs/error_log.txt     -> exceptions, PHP errors, failed logins
 *            logs/activity_log.txt  -> normal game activity (logins,
 *                                      round submissions, round processing)
 * =====================================================================
 */

if (!defined('MCQG_APP')) {
    require_once __DIR__ . '/../config/constants.php';
}

class Logger
{
    private static function writeLine(string $file, string $level, string $message): void
    {
        $path = LOG_PATH . '/' . $file;
        $line = sprintf(
            "[%s] [%s] %s%s",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            PHP_EOL
        );

        // Fail silently if the logs/ folder is not writable - logging must
        // never be the reason the actual game-play request breaks.
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }

    public static function error(string $message): void
    {
        self::writeLine('error_log.txt', 'error', $message);
    }

    public static function warning(string $message): void
    {
        self::writeLine('error_log.txt', 'warning', $message);
    }

    /** Normal, expected game activity - logins, submissions, round processing. */
    public static function activity(string $message): void
    {
        $actor = 'guest';
        if (class_exists('Session')) {
            if (Session::isAdminLoggedIn()) {
                $actor = 'admin#' . Session::currentAdminId();
            } elseif (Session::isTeamLoggedIn()) {
                $actor = 'team#' . Session::currentTeamId();
            }
        }
        self::writeLine('activity_log.txt', 'info', "[$actor] $message");
    }
}
