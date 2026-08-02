<?php
/**
 * MCQG Engine - Allocation Step 1: Minimum Guarantee
 * Path: engine/allocation/minimum_guarantee_allocation.php
 *
 * Source: MG19 Slide 12 - "First, each team is guaranteed the
 * 'minimum quantity %' of that period's total demand (the Min %
 * set up in Slide 7 / game_demand_allocation.min_percent)."
 *
 * Input:  $totalDemand (int)  - year's market_demand
 *         $minPercent (float) - game_demand_allocation.min_percent for this year
 *         $teamAvailability (array) [team_id => available_units]  (opening_inventory + production_qty)
 *
 * Output: array [team_id => units_guaranteed]
 *         Capped at each team's own available stock - a team cannot
 *         be "guaranteed" more units than it actually has to sell.
 */

function allocateMinimumGuarantee(int $totalDemand, float $minPercent, array $teamAvailability): array
{
    $guaranteedPerTeam = $totalDemand * ($minPercent / 100);

    $result = [];
    foreach ($teamAvailability as $teamId => $available) {
        $result[$teamId] = (int) min($guaranteedPerTeam, $available);
    }

    return $result;
}
