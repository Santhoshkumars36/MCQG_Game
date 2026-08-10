<?php
/**
 * MCQG AJAX (Player) - Save Investment Selection
 * Path: ajax/player_ajax/save_investment_selection.php
 * Source: MG19 Slides 10-11 - a team picks an investment amount
 * (via slider/increment field); validated here against the
 * admin-configured min/max/increment and purchase_limit before saving.
 */
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../engine/investment/validate_investment_mapping.php';
require_once __DIR__ . '/../../classes/Round.php';
require_once __DIR__ . '/../../classes/Investment.php';
Auth::requireTeam();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$db = Database::getInstance();

$teamId = Session::currentTeamId();
$gameId = Session::activeGameId();
$investmentId = (int) ($input['investment_id'] ?? 0);
$investedValue = (float) ($input['invested_value'] ?? 0);

$investment = Investment::find($investmentId);
if (!$investment) {
    Response::error('Investment not found.');
}

$amountCheck = validateInvestmentAmount(
    $investedValue,
    (float) $investment['min_investment_value'],
    (float) $investment['max_investment_value'],
    (float) $investment['increment_value']
);
if (!$amountCheck['valid']) {
    Response::error(implode(' ', $amountCheck['errors']));
}

$timesPurchased = Investment::timesPurchasedByTeam($investmentId, $teamId);
$limitCheck = validatePurchaseLimit($timesPurchased, (bool) $investment['repeat_allowed'], (int) $investment['purchase_limit']);
if (!$limitCheck['valid']) {
    Response::error(implode(' ', $limitCheck['errors']));
}

$round = Round::currentForTeam($gameId, $teamId);
if (!$round) {
    Response::error('No open round found.');
}

$decision = Round::decisionFor($teamId, $round['year_no']);
if (!$decision || $decision['status'] === 'Submitted') {
    Response::error('Cannot modify investments - decision is locked or not started.');
}

$investedPercent = $investment['max_investment_value'] > $investment['min_investment_value']
    ? (($investedValue - $investment['min_investment_value']) / ($investment['max_investment_value'] - $investment['min_investment_value'])) * 100
    : 100;

$existing = $db->fetchOne(
    "SELECT selection_id FROM team_investment_selection WHERE decision_id = :d AND investment_id = :i",
    ['d' => $decision['decision_id'], 'i' => $investmentId]
);

if ($existing) {
    $db->update('team_investment_selection',
        ['invested_value' => $investedValue, 'invested_percent' => round($investedPercent, 2)],
        'selection_id = :s', ['s' => $existing['selection_id']]
    );
} else {
    $db->insert('team_investment_selection', [
        'decision_id' => $decision['decision_id'],
        'investment_id' => $investmentId,
        'invested_value' => $investedValue,
        'invested_percent' => round($investedPercent, 2),
    ]);
}

Response::success('Investment selection saved.', ['invested_value' => $investedValue, 'invested_percent' => round($investedPercent, 2)]);
