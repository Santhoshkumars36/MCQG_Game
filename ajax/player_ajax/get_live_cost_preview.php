<?php
/**
 * MCQG AJAX (Player) - Get Live Cost Preview
 * Path: ajax/player_ajax/get_live_cost_preview.php
 * Source: MG19 Slide 10 - "the right side panel keeps changing
 * statistics... this will increase the total cost of production
 * and per-unit cost", done live as the team types.
 * Called by player-live-cost-preview.js on every input change.
 */
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../engine/cost/calculate_capacity_cost.php';
require_once __DIR__ . '/../../engine/cost/calculate_unit_cost.php';
require_once __DIR__ . '/../../engine/cost/apply_driver_effect.php';
Auth::requireTeam();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$db = Database::getInstance();

$teamId = Session::currentTeamId();
$gameId = Session::activeGameId();
$productionQty = (int) ($input['production_qty'] ?? 0);
$investments = $input['investments'] ?? []; // [investment_id => invested_value]

$game = $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]);
$team = $db->fetchOne("SELECT * FROM team_master WHERE team_id = :t", ['t' => $teamId]);

if (!$game || !$team) {
    Response::error('Game or team session not active.');
}

$capacityDrivers = $db->fetchAll("SELECT * FROM capacity_driver WHERE game_id = :g", ['g' => $gameId]);

$baseCosts = calculateCapacityDriverCosts((float) $game['capacity_cost'], $capacityDrivers);

// Build a lightweight effects list from the investments the team is
// currently previewing (not yet saved) so the preview matches what
// will actually be calculated once submitted.
$effects = [];
$totalInvestment = 0;
foreach ($investments as $investmentId => $value) {
    $value = (float) $value;
    if ($value <= 0) continue;
    $totalInvestment += $value;

    $investment = $db->fetchOne("SELECT * FROM investment_master WHERE investment_id = :i", ['i' => (int) $investmentId]);
    if (!$investment) continue;

    $percentOfRange = $investment['max_investment_value'] > $investment['min_investment_value']
        ? (($value - $investment['min_investment_value']) / ($investment['max_investment_value'] - $investment['min_investment_value'])) * 100
        : 100;

    $mappings = $db->fetchAll(
        "SELECT * FROM investment_effect WHERE investment_id = :i AND driver_type = 'Capacity'",
        ['i' => (int) $investmentId]
    );
    foreach ($mappings as $m) {
        $range = (float) $m['max_percent'] - (float) $m['min_percent'];
        $effectPercent = (float) $m['min_percent'] + ($range * (max(0, min(100, $percentOfRange)) / 100));
        $effects[] = [
            'driver_id' => $m['driver_id'],
            'effect_type' => $effectPercent >= 0 ? 'Increase' : 'Decrease',
            'effect_value' => abs($effectPercent),
            'invested_percent' => 100,
        ];
    }
}

$adjustedCosts = applyAllDriverEffects($baseCosts, $effects);
$capacityCost = array_sum(array_column($adjustedCosts, 'cost_value'));
$unitCost = calculateTeamUnitCost($adjustedCosts, $productionQty);

Response::success('Preview calculated.', [
    'capacity_cost' => round($capacityCost, 2),
    'unit_cost' => round($unitCost, 4),
    'opening_inventory' => (int) $team['opening_inventory'],
    'total_investment' => round($totalInvestment, 2),
]);
