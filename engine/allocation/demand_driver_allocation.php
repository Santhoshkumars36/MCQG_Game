<?php
/**
 * MCQG Engine - Allocation Step 2: Demand-Driver Based Share
 * Path: engine/allocation/demand_driver_allocation.php
 *
 * Source: MG19 Slide 12 - "Next, a further slice of demand is
 * allocated based on demand driver percentage. Total up all teams'
 * demand-driver investment, find each team's share of that total,
 * and multiply that share by the period's overall demand % figure."
 *
 * Input:  $remainingDemand (int)        - demand left after Step 1
 *         $demandDriverPercent (float)  - game_demand_allocation.demand_driver_percent
 *         $teamDemandInvestment (array) [team_id => total invested value in Demand Drivers this round]
 *         $teamRemainingCapacity (array)[team_id => available_units - already_guaranteed]
 *
 * Output: array [team_id => units_from_demand_driver_share]
 */

function allocateByDemandDriverShare(
    int $remainingDemand,
    float $demandDriverPercent,
    array $teamDemandInvestment,
    array $teamRemainingCapacity
): array {
    $poolForThisStep = $remainingDemand * ($demandDriverPercent / 100);
    $totalInvestment = array_sum($teamDemandInvestment);

    $result = [];
    foreach ($teamDemandInvestment as $teamId => $invested) {
        if ($totalInvestment <= 0) {
            // No team invested in demand drivers this round - split evenly as fallback
            $teamShare = 1 / max(count($teamDemandInvestment), 1);
        } else {
            $teamShare = $invested / $totalInvestment;
        }

        $unitsEarned = $poolForThisStep * $teamShare;
        $capAvailable = $teamRemainingCapacity[$teamId] ?? 0;

        $result[$teamId] = (int) min($unitsEarned, $capAvailable);
    }

    return $result;
}
