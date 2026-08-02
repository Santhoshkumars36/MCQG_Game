<?php
/**
 * MCQG Engine - Unit Cost Calculation
 * Path: engine/cost/calculate_unit_cost.php
 *
 * Source: MG19 Slide 4 - "Unit cost = Capacity cost / Capacity"
 *         (baseline, game-level, read-only/auto-calculated).
 * Source: MG19 Slide 10 - "the right side panel keeps changing...
 *         cost per unit" as a team invests in Capacity Drivers.
 */

// Baseline unit cost before any team investment effects (game_master.unit_cost)
function calculateBaselineUnitCost(float $capacityCost, int $capacity): float
{
    if ($capacity <= 0) {
        return 0.0;
    }
    return round($capacityCost / $capacity, 4);
}

/**
 * Team-specific unit cost, after applying each capacity driver's
 * adjusted cost_value (post investment-effect) across the team's
 * own production quantity.
 *
 * Input: $adjustedDriverCosts (array) - output of applyDriverEffect()
 *        for every capacity driver, already adjusted for this team
 *        $productionQty (int)
 */
function calculateTeamUnitCost(array $adjustedDriverCosts, int $productionQty): float
{
    if ($productionQty <= 0) {
        return 0.0;
    }
    $totalCost = array_sum(array_column($adjustedDriverCosts, 'cost_value'));
    return round($totalCost / $productionQty, 4);
}
