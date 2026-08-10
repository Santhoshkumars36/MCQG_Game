<?php
/**
 * MCQG AJAX - Check if Team Name or Username exists
 * Path: ajax/player_ajax/check_team_exists.php
 */
require_once __DIR__ . '/../../config/app_config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$teamName = trim($input['team_name'] ?? '');
$username = trim($input['username'] ?? '');

$db = Database::getInstance();

$teamNameExists = false;
if ($teamName !== '') {
    $row = $db->fetchOne("SELECT team_id FROM team_master WHERE LOWER(TRIM(team_name)) = LOWER(:tn)", ['tn' => $teamName]);
    $teamNameExists = (bool)$row;
}

$usernameExists = false;
if ($username !== '') {
    $row = $db->fetchOne("SELECT team_id FROM team_master WHERE LOWER(TRIM(username)) = LOWER(:u)", ['u' => $username]);
    $usernameExists = (bool)$row;
}

Response::success([
    'team_name_exists' => $teamNameExists,
    'username_exists'  => $usernameExists,
]);
