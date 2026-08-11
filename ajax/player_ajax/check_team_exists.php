<?php
/**
 * MCQG AJAX - Check if Team Name or Username exists (per-game)
 * Path: ajax/player_ajax/check_team_exists.php
 * 
 * Validates uniqueness within a specific game session:
 * - Same username/team_name CAN register for different games
 * - Same username/team_name CANNOT register for the same game twice
 */
require_once __DIR__ . '/../../config/app_config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$teamName = trim($input['team_name'] ?? '');
$username = trim($input['username'] ?? '');
$gameId = (int)($input['game_id'] ?? 0);

$db = Database::getInstance();

$teamNameExists = false;
if ($teamName !== '' && $gameId > 0) {
    $row = $db->fetchOne(
        "SELECT team_id FROM team_master WHERE LOWER(TRIM(team_name)) = LOWER(:tn) AND game_id = :gid",
        ['tn' => $teamName, 'gid' => $gameId]
    );
    $teamNameExists = (bool)$row;
}

$usernameExists = false;
if ($username !== '' && $gameId > 0) {
    $row = $db->fetchOne(
        "SELECT team_id FROM team_master WHERE LOWER(TRIM(username)) = LOWER(:u) AND game_id = :gid",
        ['u' => $username, 'gid' => $gameId]
    );
    $usernameExists = (bool)$row;
}

Response::success([
    'team_name_exists' => $teamNameExists,
    'username_exists'  => $usernameExists,
]);
