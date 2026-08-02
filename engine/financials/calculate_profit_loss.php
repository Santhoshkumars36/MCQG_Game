<?php
/**
 * MCQG Engine - Profit / Loss Calculation
 * Path: engine/financials/calculate_profit_loss.php
 *
 * Source: Doc 1 "Financial Performance" lists Variable Costs, Fixed
 * Costs, Investment Costs, and Operating Profit as required outputs.
 *
 * IMPORTANT - FLAGGED GAP (raised during requirement analysis):
 * none of the 3 source documents specify the exact cost formula
 * (e.g. whether investment cost is expensed immediately or
 * amortized, or how inflation compounds). The formula below uses
 * the most standard, defensible approach and is clearly isolated
 * here so it can be swapped out in one place if the real business
 * rule is confirmed later.
 */

function calculateVariableCost(int $unitsSold, float $unitCost): float
{
    return round($unitsSold * $unitCost, 2);
}

function calculateFixedCost(array $capacityDriverCosts, int $productionQty): float
{
    // Fixed portion = cost tied to capacity itself, not to units sold
    // (kept isolated so it can be redefined without touching other files)
    return round(array_sum(array_column($capacityDriverCosts, 'cost_value')) * 0.10, 2);
}

/**
 * Investment cost this round - assumed fully expensed in the round
 * it was purchased (simplest, most transparent assumption; flagged
 * as unconfirmed against source documents).
 */
function calculateInvestmentCost(array $teamInvestmentSelections): float
{
    return round(array_sum(array_column($teamInvestmentSelections, 'invested_value')), 2);
}

function calculateOperatingProfit(
    float $revenue,
    float $variableCost,
    float $fixedCost,
    float $investmentCost
): float {
    return round($revenue - $variableCost - $fixedCost - $investmentCost, 2);
}

/**
 * Running cash position = previous round's cash + this round's profit.
 * (Doc 1: "Cash Position" is a listed financial output.)
 */
function calculateCashPosition(float $previousCash, float $operatingProfitThisRound): float
{
    return round($previousCash + $operatingProfitThisRound, 2);
}
