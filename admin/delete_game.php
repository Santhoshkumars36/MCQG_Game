<?php
/**
 * MCQG Admin - Delete Game
 * Path: admin/delete_game.php
 */
require_once __DIR__ . '/../config/app_config.php';
Auth::requireAdmin();

$gameId = (int) ($_GET['game_id'] ?? 0);
if ($gameId > 0) {
    $db = Database::getInstance();
    $game = $db->fetchOne("SELECT game_name FROM game_master WHERE game_id = :id", ['id' => $gameId]);
    if ($game) {
        $db->query("DELETE FROM game_master WHERE game_id = :id", ['id' => $gameId]);
        if (Session::get('setup_game_id') == $gameId) {
            Session::delete('setup_game_id');
        }
        Session::setFlash('success', 'Game "' . htmlspecialchars($game['game_name']) . '" deleted successfully.');
    } else {
        Session::setFlash('error', 'Game not found.');
    }
} else {
    Session::setFlash('error', 'Invalid game ID.');
}

$redirect = $_SERVER['HTTP_REFERER'] ?? (ADMIN_URL . 'manage_games.php');
if (strpos($redirect, 'delete_game.php') !== false) {
    $redirect = ADMIN_URL . 'manage_games.php';
}
header("Location: " . $redirect);
exit;
