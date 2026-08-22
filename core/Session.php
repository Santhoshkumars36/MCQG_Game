<?php
/**
 * =====================================================================
 * MCQG - core/Session.php
 * Purpose: Clean, typed wrapper around PHP's $_SESSION superglobal,
 *          plus one-time "flash" messages used for the animated
 *          toast/alert notifications required by the interactive
 *          design (e.g. "Saved successfully", "Totals must equal 100%").
 * =====================================================================
 */

if (!defined('MCQG_APP')) {
    require_once __DIR__ . '/../config/constants.php';
}

class Session
{
    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroyAll(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /**
     * Store a one-time message (type: success | error | warning | info)
     * to be shown as an animated toast on the NEXT page load, then
     * automatically cleared. Used across admin + player modules.
     */
    public static function setFlash(string $type, string $message): void
    {
        $_SESSION[SESSION_FLASH_MESSAGE] = ['type' => $type, 'message' => $message];
    }

    /** Reads and immediately clears the flash message. */
    public static function getFlash(): ?array
    {
        if (!isset($_SESSION[SESSION_FLASH_MESSAGE])) {
            return null;
        }
        $flash = $_SESSION[SESSION_FLASH_MESSAGE];
        unset($_SESSION[SESSION_FLASH_MESSAGE]);
        return $flash;
    }

    /** Returns the current CSRF token (generated in session_config.php). */
    public static function csrfToken(): string
    {
        return $_SESSION[SESSION_CSRF_TOKEN] ?? '';
    }

    // -------------------------------------------------------------
    // Convenience helpers used throughout admin/ and player/ pages
    // -------------------------------------------------------------
    public static function isAdminLoggedIn(): bool
    {
        return self::has(SESSION_ADMIN_ID);
    }

    public static function isTeamLoggedIn(): bool
    {
        return self::has(SESSION_TEAM_ID) || self::has('mcqg_team_username');
    }

    public static function currentTeamUsername(): ?string
    {
        return self::get('mcqg_team_username') ?? self::get(SESSION_TEAM_NAME);
    }

    public static function currentAdminId(): ?int
    {
        return self::get(SESSION_ADMIN_ID);
    }

    public static function currentTeamId(): ?int
    {
        return self::get(SESSION_TEAM_ID);
    }

    public static function activeGameId(): ?int
    {
        return self::get(SESSION_GAME_ID);
    }
}
