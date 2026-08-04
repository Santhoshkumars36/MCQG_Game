<?php
/**
 * =====================================================================
 * MCQG - core/Response.php
 * Purpose: Every file in ajax/admin_ajax/ and ajax/player_ajax/ returns
 *          data through this class, so the front-end JS always
 *          receives one predictable JSON shape:
 *
 *              { "success": true,  "data": {...}, "message": "..." }
 *              { "success": false, "errors": [...], "message": "..." }
 *
 *          This is what the live cost-preview, live percentage-total
 *          checker, and live price validation (required by the
 *          "highly interactive" design) all rely on.
 * =====================================================================
 */

if (!defined('MCQG_APP')) {
    require_once __DIR__ . '/../config/constants.php';
}

class Response
{
    public static function success($data = null, string $message = ''): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ]);
        exit;
    }

    public static function error(string $message, array $errors = [], int $httpCode = 400): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ]);
        exit;
    }

    /** Shortcut for "you must be logged in" responses from ajax/ files. */
    public static function unauthorized(string $message = 'Please log in again.'): void
    {
        self::error($message, [], 401);
    }
}
