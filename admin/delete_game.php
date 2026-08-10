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
        try {
            $db->execute("SET FOREIGN_KEY_CHECKS = 0");
            $db->execute("DELETE FROM team_investment_selection WHERE decision_id IN (SELECT decision_id FROM team_decision WHERE game_id = :id)", ['id' => $gameId]);
            $db->execute("DELETE FROM team_result WHERE game_id = :id", ['id' => $gameId]);
            $db->execute("DELETE FROM team_decision WHERE game_id = :id", ['id' => $gameId]);
            $db->execute("DELETE FROM team_master WHERE game_id = :id", ['id' => $gameId]);
            $db->execute("DELETE FROM game_round_status WHERE game_id = :id", ['id' => $gameId]);
            $db->execute("DELETE FROM game_demand_allocation WHERE game_id = :id", ['id' => $gameId]);
            $db->execute("DELETE FROM game_configuration WHERE game_id = :id", ['id' => $gameId]);
            $db->execute("DELETE FROM investment_effect WHERE investment_id IN (SELECT investment_id FROM investment_master WHERE game_id = :id)", ['id' => $gameId]);
            $db->execute("DELETE FROM investment_master WHERE game_id = :id", ['id' => $gameId]);
            $db->execute("DELETE FROM capacity_driver WHERE game_id = :id", ['id' => $gameId]);
            $db->execute("DELETE FROM demand_driver WHERE game_id = :id", ['id' => $gameId]);
            $db->execute("DELETE FROM game_market_year WHERE game_id = :id", ['id' => $gameId]);
            $db->execute("DELETE FROM game_master WHERE game_id = :id", ['id' => $gameId]);
            $db->execute("SET FOREIGN_KEY_CHECKS = 1");

            if (Session::get('setup_game_id') == $gameId) {
                Session::delete('setup_game_id');
            }
            Session::setFlash('success', 'Game "' . $game['game_name'] . '" deleted successfully.');
        } catch (Exception $e) {
            $db->execute("SET FOREIGN_KEY_CHECKS = 1");
            Session::setFlash('error', 'Failed to delete game: ' . $e->getMessage());
        }
    } else {
        Session::setFlash('error', 'Game not found.');
    }
} else {
    Session::setFlash('error', 'Invalid game ID.');
}

$redirect = $_SERVER['HTTP_REFERER'] ?? (ADMIN_URL . 'dashboard.php');
if (strpos($redirect, 'delete_game.php') !== false) {
    $redirect = ADMIN_URL . 'dashboard.php';
}
header("Location: " . $redirect);
exit;
