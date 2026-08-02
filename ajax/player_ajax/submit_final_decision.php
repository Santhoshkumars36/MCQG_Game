<?php
/**
 * MCQG AJAX (Player) - Submit Final Decision
 * Path: ajax/player_ajax/submit_final_decision.php
 * Source: MG19 Slide 15 - locks a team's decision for the round.
 * This is the AJAX counterpart to player/screens/submit_decision.php,
 * used so the Review & Submit screen can confirm and lock a decision
 * without a full page reload (per the "highly interactive" requirement).
 */
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../classes/Round.php';
Auth::requireTeam();

$teamId = Session::get('team_id');
$gameId = Session::get('game_id');

$round = Round::currentForTeam($gameId, $teamId);
if (!$round) {
    Response::error('No open round found to submit.');
}

$decision = Round::decisionFor($teamId, $round['year_no']);
if (!$decision) {
    Response::error('Please complete Build Production and Demand Economics before submitting.');
}
if ($decision['status'] === 'Submitted') {
    Response::error('This decision has already been submitted and is locked.');
}
if ((int) $decision['production_qty'] <= 0 || (float) $decision['selling_price'] <= 0) {
    Response::error('Production quantity and selling price must both be set before submitting.');
}

Round::submitDecision($decision['decision_id']);
Logger::activity("Team {$teamId} submitted decision for Year {$round['year_no']} via AJAX (game {$gameId}).");

Response::success("Your Year {$round['year_no']} decision has been submitted and locked.", [
    'year_no' => $round['year_no'],
    'redirect' => PLAYER_URL . 'screens/review_decision.php',
]);
