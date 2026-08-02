<?php
/**
 * MCQG Engine - Inventory Carrying Calculation
 * Path: engine/financials/calculate_inventory_carrying.php
 *
 * Source: MG19 Slide 13 (exact quote) - "Inventory carrying =
 * opening inventory + capacity produced - sold."
 */

function calculateClosingInventory(int $openingInventory, int $productionQty, int $unitsSold): int
{
    return $openingInventory + $productionQty - $unitsSold;
}

/** Capacity Utilization % - Doc 1 "Financial Performance" output */
function calculateCapacityUtilization(int $productionQty, int $capacity): float
{
    if ($capacity <= 0) {
        return 0.0;
    }
    return round(($productionQty / $capacity) * 100, 2);
}
