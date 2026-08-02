<?php
/**
 * MCQG Engine - Allocation Step 3: Price-Based Leftover (Lowest Price First)
 * Path: engine/allocation/price_based_allocation.php
 *
 * Source: MG19 Slide 12 / Doc 1 "Market Behaviour" -
 * "Whatever demand is still left over after Steps 1 and 2 is
 * allocated to teams strictly by lowest price first, continuing
 * until demand runs out or a team's full capacity is used -
 * whichever comes first."
 *
 * Input:  $remainingDemand (int)
 *         $teamPrices (array)          [team_id => selling_price]
 *         $teamRemainingCapacity(array)[team_id => units still unsold after Steps 1 & 2]
 *
 * Output: array [team_id => units_from_price_waterfall]
 */

function allocateByLowestPrice(int $remainingDemand, array $teamPrices, array $teamRemainingCapacity): array
{
    // Sort teams cheapest price first (ties keep original array order)
    asort($teamPrices);

    $result = [];
    foreach (array_keys($teamPrices) as $teamId) {
        $result[$teamId] = 0;
    }

    foreach ($teamPrices as $teamId => $price) {
        if ($remainingDemand <= 0) {
            break;
        }
        $available = $teamRemainingCapacity[$teamId] ?? 0;
        $take = min($available, $remainingDemand);

        $result[$teamId] = $take;
        $remainingDemand -= $take;
    }

    return $result;
}
