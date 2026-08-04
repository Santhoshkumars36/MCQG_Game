<?php
/**
 * MCQG - Shared Auth Guard (optional consolidated version)
 * Path: includes/auth_check.php
 *
 * NOTE ON USAGE: every page already delivered under admin/*.php and
 * player/*.php calls Auth::requireAdmin() or Auth::requireTeam()
 * directly, right after loading config/app_config.php - so those
 * pages do NOT need this file and already work without it. This file
 * is provided as an OPTIONAL single-guard alternative for anyone who
 * prefers one shared include over repeating the direct calls, and
 * it auto-detects which module a page belongs to from its own path.
 *
 * Usage (top of any admin/*.php or player/*.php page, AFTER app_config.php):
 *   require_once __DIR__ . '/../../config/app_config.php';
 *   require_once __DIR__ . '/../../includes/auth_check.php';
 */

$currentScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

if (str_contains($currentScript, '/admin/')) {
    Auth::requireAdmin();
} elseif (str_contains($currentScript, '/player/')) {
    Auth::requireTeam();

    // Extra player-side guard: a team may only act inside a game that
    // is still Published. If it was reverted to Draft or no longer
    // exists, force a fresh login rather than showing a broken screen.
    $activeGameId = Session::get('game_id');
    if ($activeGameId) {
        require_once __DIR__ . '/../classes/Game.php';
        $game = Game::find((int) $activeGameId);
        if (!$game || $game['status'] !== GAME_STATUS_PUBLISHED) {
            Auth::logout();
            Session::setFlash('error', 'This game is no longer available. Please contact your administrator.');
            header('Location: ' . PLAYER_URL . 'auth/login.php');
            exit;
        }
    }
} else {
    // A page outside both admin/ and player/ tried to include this
    // guard - fail safe by refusing access rather than guessing.
    http_response_code(403);
    die('Access denied: this page must be accessed through the admin or player module.');
}
