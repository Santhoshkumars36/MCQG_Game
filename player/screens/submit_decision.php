<?php
/**
 * MCQG Player - Submit Final Decision
 * Path: player/screens/submit_decision.php
 * Source: MG19 Slide 15 - once submitted, a team's decision is
 * locked for that round; results are only processed once every
 * team has submitted (checked in admin/round_control/process_round.php).
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireTeam();

$db = Database::getInstance();
$teamId = Session::get('team_id');
$gameId = Session::get('game_id');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: review_decision.php');
    exit;
}

$currentRound = $db->fetchOne(
    "SELECT * FROM game_round_status WHERE game_id = :g AND status != 'Processed' ORDER BY year_no LIMIT 1",
    ['g' => $gameId]
);

if (!$currentRound) {
    Session::setFlash('error', 'No open round found to submit.');
    header('Location: ' . PLAYER_URL . 'dashboard.php');
    exit;
}

$yearNo = $currentRound['year_no'];
$decision = $db->fetchOne(
    "SELECT * FROM team_decision WHERE team_id = :t AND year_no = :y",
    ['t' => $teamId, 'y' => $yearNo]
);

if (!$decision) {
    Session::setFlash('error', 'Please complete Build Production and Demand Economics before submitting.');
    header('Location: ' . PLAYER_URL . 'screens/build_production.php');
    exit;
}

if ($decision['status'] === 'Submitted') {
    Session::setFlash('error', 'This decision has already been submitted and is locked.');
    header('Location: review_decision.php');
    exit;
}

// Final sanity checks before locking - production must be set and price must exist
if ((int) $decision['production_qty'] <= 0 || (float) $decision['selling_price'] <= 0) {
    Session::setFlash('error', 'Production quantity and selling price must both be set before submitting.');
    header('Location: review_decision.php');
    exit;
}

$db->update(
    'team_decision',
    ['status' => 'Submitted', 'submitted_on' => date('Y-m-d H:i:s')],
    'decision_id = :d',
    ['d' => $decision['decision_id']]
);

Logger::activity("Team {$teamId} submitted decision for Year {$yearNo} (game {$gameId}).");
Session::setFlash('success', "Your Year {$yearNo} decision has been submitted and locked. Waiting for other teams and the admin to process the round.");

header('Location: review_decision.php');
exit;
