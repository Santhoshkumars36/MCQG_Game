<?php
/**
 * MCQG Engine - Bootstrap / Orchestrator
 * Path: engine/engine_bootstrap.php
 *
 * This is the file admin/round_control/process_round.php calls.
 * It runs the FULL pipeline for one game-year, in the exact order
 * described across Doc 1, Doc 2, and MG19 Slide 12:
 *
 *   1. Read Financial Parameters   (capacity/demand drivers, market year)
 *   2. Apply Investment Effects    (engine/investment/*)
 *   3. Allocate units sold         (engine/allocation/* - 3 steps)
 *   4. Calculate cost per unit     (engine/cost/*)
 *   5. Calculate financials        (engine/financials/*)
 *   6. Save to team_result + mark round Processed
 */

require_once __DIR__ . '/allocation/minimum_guarantee_allocation.php';
require_once __DIR__ . '/allocation/demand_driver_allocation.php';
require_once __DIR__ . '/allocation/price_based_allocation.php';
require_once __DIR__ . '/cost/calculate_capacity_cost.php';
require_once __DIR__ . '/cost/calculate_unit_cost.php';
require_once __DIR__ . '/cost/apply_driver_effect.php';
require_once __DIR__ . '/investment/apply_investment_effects.php';
require_once __DIR__ . '/investment/validate_investment_mapping.php';
require_once __DIR__ . '/financials/calculate_revenue.php';
require_once __DIR__ . '/financials/calculate_profit_loss.php';
require_once __DIR__ . '/financials/calculate_inventory_carrying.php';

function processGameRound(int $gameId, int $yearNo): array
{
    $db = Database::getInstance();

    // --- Step 1: Read Financial Parameters -----------------------------
    $game = $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]);
    $marketYear = $db->fetchOne(
        "SELECT * FROM game_market_year WHERE game_id = :g AND year_no = :y",
        ['g' => $gameId, 'y' => $yearNo]
    );
    $allocationRule = $db->fetchOne(
        "SELECT * FROM game_demand_allocation WHERE game_id = :g AND year_no = :y",
        ['g' => $gameId, 'y' => $yearNo]
    );
    $capacityDrivers = $db->fetchAll("SELECT * FROM capacity_driver WHERE game_id = :g", ['g' => $gameId]);
    $decisions = $db->fetchAll(
        "SELECT * FROM team_decision WHERE game_id = :g AND year_no = :y AND status = 'Submitted'",
        ['g' => $gameId, 'y' => $yearNo]
    );

    if (count($decisions) === 0) {
        return ['success' => false, 'message' => 'No submitted decisions found for this round.'];
    }

    $teamAvailability = [];
    $teamPrices = [];
    $teamDemandInvestment = [];
    $teamEffectsByTeam = [];

    // --- Step 2: Apply Investment Effects, per team ---------------------
    foreach ($decisions as $decision) {
        $teamId = $decision['team_id'];
        $team = $db->fetchOne("SELECT * FROM team_master WHERE team_id = :t", ['t' => $teamId]);

        $available = (int) $team['opening_inventory'] + (int) $decision['production_qty'];
        $teamAvailability[$teamId] = $available;
        $teamPrices[$teamId] = (float) $decision['selling_price'];

        $effects = getTeamInvestmentEffects($decision['decision_id']);
        $split = splitEffectsByDriverType($effects);
        $teamEffectsByTeam[$teamId] = $split;

        $teamDemandInvestment[$teamId] = array_sum(array_column($split['demand'], 'invested_value'));
    }

    // --- Step 3: Allocate units sold (3-step waterfall) ------------------
    $totalDemand = (int) $marketYear['market_demand'];

    $step1 = allocateMinimumGuarantee($totalDemand, (float) $allocationRule['min_percent'], $teamAvailability);

    $remainingAfterStep1 = $totalDemand - array_sum($step1);
    $remainingCapacityAfterStep1 = [];
    foreach ($teamAvailability as $teamId => $avail) {
        $remainingCapacityAfterStep1[$teamId] = $avail - $step1[$teamId];
    }

    $step2 = allocateByDemandDriverShare(
        $remainingAfterStep1,
        (float) $allocationRule['demand_driver_percent'],
        $teamDemandInvestment,
        $remainingCapacityAfterStep1
    );

    $remainingAfterStep2 = $remainingAfterStep1 - array_sum($step2);
    $remainingCapacityAfterStep2 = [];
    foreach ($remainingCapacityAfterStep1 as $teamId => $avail) {
        $remainingCapacityAfterStep2[$teamId] = $avail - $step2[$teamId];
    }

    $step3 = allocateByLowestPrice($remainingAfterStep2, $teamPrices, $remainingCapacityAfterStep2);

    // --- Step 4 & 5: Cost + Financials, per team --------------------------
    $results = [];
    foreach ($decisions as $decision) {
        $teamId = $decision['team_id'];
        $team = $db->fetchOne("SELECT * FROM team_master WHERE team_id = :t", ['t' => $teamId]);

        $unitsSold = $step1[$teamId] + $step2[$teamId] + $step3[$teamId];

        $baseCosts = calculateCapacityDriverCosts((float) $game['capacity_cost'], $capacityDrivers);
        $adjustedCosts = applyAllDriverEffects($baseCosts, $teamEffectsByTeam[$teamId]['capacity']);
        $unitCost = calculateTeamUnitCost($adjustedCosts, (int) $decision['production_qty']);

        $revenue = calculateRevenue($unitsSold, (float) $decision['selling_price']);
        $variableCost = calculateVariableCost($unitsSold, $unitCost);
        $fixedCost = calculateFixedCost($adjustedCosts, (int) $decision['production_qty']);

        $investments = $db->fetchAll(
            "SELECT invested_value FROM team_investment_selection WHERE decision_id = :d",
            ['d' => $decision['decision_id']]
        );
        $investmentCost = calculateInvestmentCost($investments);

        $operatingProfit = calculateOperatingProfit($revenue, $variableCost, $fixedCost, $investmentCost);
        $closingInventory = calculateClosingInventory((int) $team['opening_inventory'], (int) $decision['production_qty'], $unitsSold);
        $capacityUtil = calculateCapacityUtilization((int) $decision['production_qty'], (int) $game['starting_capacity']);

        $previousResult = $db->fetchOne(
            "SELECT cash_position FROM team_result WHERE team_id = :t AND year_no = :y",
            ['t' => $teamId, 'y' => $yearNo - 1]
        );
        $previousCash = $previousResult ? (float) $previousResult['cash_position'] : (float) $team['opening_budget'];
        $cashPosition = calculateCashPosition($previousCash, $operatingProfit);

        // --- Step 6: Save result --------------------------------------------
        $db->insert('team_result', [
            'team_id' => $teamId,
            'game_id' => $gameId,
            'year_no' => $yearNo,
            'units_sold' => $unitsSold,
            'closing_inventory' => $closingInventory,
            'capacity_utilization_percent' => $capacityUtil,
            'service_level_percent' => null, // FLAGGED GAP - formula not defined in source docs
            'revenue' => $revenue,
            'variable_cost' => $variableCost,
            'fixed_cost' => $fixedCost,
            'investment_cost' => $investmentCost,
            'operating_profit' => $operatingProfit,
            'cash_position' => $cashPosition,
        ]);

        $results[$teamId] = compact('unitsSold', 'revenue', 'operatingProfit', 'closingInventory');
    }

    $db->update('game_round_status',
        ['status' => ROUND_STATUS_PROCESSED, 'processed_on' => date('Y-m-d H:i:s'), 'processed_by' => Session::get('admin_id')],
        'game_id = :g AND year_no = :y',
        ['g' => $gameId, 'y' => $yearNo]
    );

    Logger::activity("Round {$yearNo} processed for game {$gameId}.");

    return ['success' => true, 'message' => 'Round processed successfully.', 'results' => $results];
}
