<?php
/**
 * MCQG Engine - Capacity Cost Calculation
 * Path: engine/cost/calculate_capacity_cost.php
 *
 * Source: Doc 2 (Admin/DB Design) Entity 3 - "Cost is calculated
 * automatically: Capacity cost x Cost share %."
 * Source: MG19 Slide 4 - Capacity cost is a game-level entry field.
 *
 * Input:  $capacityCost (float)        - game_master.capacity_cost
 *         $capacityDrivers (array)     - rows from capacity_driver table
 *                                        each: ['driver_id', 'driver_name', 'cost_share_percent']
 *
 * Output: array [driver_id => ['driver_name' => ..., 'cost_value' => ...]]
 */

function calculateCapacityDriverCosts(float $capacityCost, array $capacityDrivers): array
{
    $result = [];
    foreach ($capacityDrivers as $driver) {
        $costValue = $capacityCost * ((float) $driver['cost_share_percent'] / 100);
        $result[$driver['driver_id']] = [
            'driver_name' => $driver['driver_name'],
            'cost_value'  => round($costValue, 4),
        ];
    }
    return $result;
}

/**
 * Total capacity cost for a team, including any Capacity Expansion
 * investment they purchased this round (e.g. investment_id 7 in seed data).
 */
function calculateTeamTotalCapacityCost(float $baseCapacityCost, float $capacityExpansionInvestment = 0): float
{
    return round($baseCapacityCost + $capacityExpansionInvestment, 2);
}
