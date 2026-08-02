<?php
/**
 * MCQG Engine - Revenue Calculation
 * Path: engine/financials/calculate_revenue.php
 *
 * Source: Doc 1 "Financial Performance" (Revenue) + verified exactly
 * against the Sample Game Narrative document (all 3 teams' Round 1
 * revenue figures matched this formula to the rupee).
 */

function calculateRevenue(int $unitsSold, float $sellingPrice): float
{
    return round($unitsSold * $sellingPrice, 2);
}
