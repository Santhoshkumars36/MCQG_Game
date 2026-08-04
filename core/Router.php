<?php
/**
 * =====================================================================
 * MCQG - core/Router.php
 * Purpose: Small helper functions for building URLs and asset paths
 *          consistently across the whole app - so admin/ pages and
 *          player/ pages each pull CSS/JS ONLY from their own separate
 *          assets/ folder (as required), never mixed up, and never
 *          hardcoded as a raw string in HTML.
 * =====================================================================
 */

if (!defined('MCQG_APP')) {
    require_once __DIR__ . '/../config/constants.php';
}

class Router
{
    /** Redirect the browser and stop execution. */
    public static function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    /** Full URL for a page relative to the app's base URL. */
    public static function url(string $path = ''): string
    {
        return BASE_URL . '/' . ltrim($path, '/');
    }

    /** Shared asset (Bootstrap, jQuery) - assets/css/... or assets/js/... */
    public static function sharedAsset(string $path): string
    {
        return ASSETS_URL . '/' . ltrim($path, '/');
    }

    /** Admin-only asset - admin/assets/css/... or admin/assets/js/... */
    public static function adminAsset(string $path): string
    {
        return ADMIN_ASSETS_URL . '/' . ltrim($path, '/');
    }

    /** Player-only asset - player/assets/css/... or player/assets/js/... */
    public static function playerAsset(string $path): string
    {
        return PLAYER_ASSETS_URL . '/' . ltrim($path, '/');
    }

    /** Endpoint URL inside ajax/admin_ajax/ - used by admin JS fetch() calls. */
    public static function adminAjax(string $file): string
    {
        return AJAX_URL . '/admin_ajax/' . ltrim($file, '/');
    }

    /** Endpoint URL inside ajax/player_ajax/ - used by player JS fetch() calls. */
    public static function playerAjax(string $file): string
    {
        return AJAX_URL . '/player_ajax/' . ltrim($file, '/');
    }

    /** Current full request path, useful for highlighting the active nav link. */
    public static function currentPath(): string
    {
        return parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    }

    /** True if $path matches (or starts with) the current request path - for nav "active" styling. */
    public static function isActive(string $path): bool
    {
        return str_contains(self::currentPath(), $path);
    }
}
