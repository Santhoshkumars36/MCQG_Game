<?php
/**
 * MCQG AJAX (Player) - Validate Price Range
 * Path: ajax/player_ajax/validate_price_range.php
 * Source: MG19 Slide 11 - sale price validated against Min/Max
 * range live, plus an estimated Profit or Loss shown on screen.
 */
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../engine/financials/calculate_revenue.php';
Auth::requireTeam();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$db = Database::getInstance();

$teamId = Session::get('team_id');
$gameId = Session::get('game_id');
$price = (float) ($input['selling_price'] ?? 0);
$productionQty = (int) ($input['production_qty'] ?? 0);
$estimatedUnitCost = (float) ($input['estimated_unit_cost'] ?? 0);

$minPriceRow = $db->fetchOne(
    "SELECT configuration_value FROM game_configuration WHERE game_id = :g AND configuration_key = 'Minimum Selling Price'",
    ['g' => $gameId]
);
$maxPriceRow = $db->fetchOne(
    "SELECT configuration_value FROM game_configuration WHERE game_id = :g AND configuration_key = 'Maximum Selling Price'",
    ['g' => $gameId]
);
$minPrice = $minPriceRow ? (float) $minPriceRow['configuration_value'] : 0;
$maxPrice = $maxPriceRow ? (float) $maxPriceRow['configuration_value'] : 999999;

$withinRange = Validator::priceWithinRange($price, $minPrice, $maxPrice);

// Rough estimate only - actual profit depends on the demand allocation
// engine once the round is processed (engine/allocation/*).
$estimatedRevenue = calculateRevenue($productionQty, $price);
$estimatedCost = $productionQty * $estimatedUnitCost;
$estimatedProfit = $estimatedRevenue - $estimatedCost;

Response::success('Price validated.', [
    'within_range' => $withinRange,
    'min_price' => $minPrice,
    'max_price' => $maxPrice,
    'estimated_profit' => round($estimatedProfit, 2),
]);
