<?php
/**
 * MCQG Player - Select Game Handler
 * Path: player/select_game.php
 * Purpose: Handles clicking the "Play" button on Team Registration Hub.
 *  - Activates the selected game session and team context.
 *  - Enforces Point 6 restriction: blocks entry if Round 1 is announced
 *    and team is a newly registered player that did not participate in Round 1.
 *  - Navigates to normal Play Team module landing page.
 */
require_once __DIR__ . '/../config/app_config.php';
Auth::requireTeam();

$db = Database::getInstance();
$teamUsername = Session::currentTeamUsername() ?: Session::get(SESSION_TEAM_NAME);
$gameId = (int)($_GET['game_id'] ?? 0);

if (!$gameId) {
    header('Location: ' . PLAYER_URL . 'team_registration.php');
    exit;
}

// Fetch game master
$game = $db->fetchOne(
    "SELECT * FROM game_master WHERE game_id = :g AND status = :s AND (isDeleted IS NULL OR isDeleted = 0)",
    ['g' => $gameId, 's' => GAME_STATUS_PUBLISHED]
);

if (!$game) {
    header('Location: ' . PLAYER_URL . 'team_registration.php');
    exit;
}

// Check team registration
$registeredTeam = null;
if ($teamUsername) {
    $registeredTeam = $db->fetchOne(
        "SELECT * FROM team_master WHERE game_id = :g AND LOWER(TRIM(username)) = LOWER(:u) AND (isDeleted IS NULL OR isDeleted = 0)",
        ['g' => $gameId, 'u' => trim($teamUsername)]
    );
}

// Fallback check by current session team_id
if (!$registeredTeam && Session::currentTeamId() && Session::activeGameId() === $gameId) {
    $registeredTeam = $db->fetchOne(
        "SELECT * FROM team_master WHERE team_id = :t AND game_id = :g",
        ['t' => Session::currentTeamId(), 'g' => $gameId]
    );
}

if (!$registeredTeam) {
    // Team is not registered for this game yet -> redirect to game registration page
    header('Location: ' . PLAYER_URL . 'game_register.php?game_id=' . $gameId);
    exit;
}

$teamId = (int)$registeredTeam['team_id'];

// Check Point 6 restriction: Round 1 announced / released check
$round1Status = $db->fetchOne(
    "SELECT status FROM game_round_status WHERE game_id = :g AND year_no = 1",
    ['g' => $gameId]
);
$resultCountR1 = (int)$db->fetchOne(
    "SELECT COUNT(*) AS c FROM team_result WHERE game_id = :g AND year_no = 1",
    ['g' => $gameId]
)['c'];

$isFirstRoundReleased = ($round1Status && $round1Status['status'] === ROUND_STATUS_PROCESSED) || ($resultCountR1 > 0);

// Check if team participated in Round 1
$round1Decision = $db->fetchOne(
    "SELECT * FROM team_decision WHERE team_id = :t AND year_no = 1",
    ['t' => $teamId]
);

// If first round is released AND newly registered team didn't participate in Round 1 -> Block!
if ($isFirstRoundReleased && empty($round1Decision)) {
    Logger::warning("Team '{$registeredTeam['team_name']}' attempted to join Game #{$gameId} after Round 1 was released.");
    header('Location: ' . PLAYER_URL . 'team_registration.php?error=game_started');
    exit;
}

// Set active session context
Session::set(SESSION_GAME_ID, $gameId);
Session::set(SESSION_TEAM_ID, $teamId);
Session::set(SESSION_TEAM_NAME, $registeredTeam['team_name']);

Logger::activity("Team '{$registeredTeam['team_name']}' launched into Game #{$gameId}.");

// Navigate to normal Play Team module landing page (Point 5)
header('Location: ' . PLAYER_URL . 'landing.php');
exit;
