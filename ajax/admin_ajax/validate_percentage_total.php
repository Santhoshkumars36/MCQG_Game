<?php
/**
 * MCQG AJAX (Admin) - Validate Percentage Total
 * Path: ajax/admin_ajax/validate_percentage_total.php
 * Source: MG19 Slide 5 - Cost share % / Demand share % must total
 * exactly 100%. Called live from admin-percentage-check.js, and
 * again authoritatively before any SAVE completes.
 */
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../classes/CapacityDriver.php';
require_once __DIR__ . '/../../classes/DemandDriver.php';
Auth::requireAdmin();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$gameId = (int) ($input['game_id'] ?? 0);
$driverType = $input['driver_type'] ?? 'capacity'; // 'capacity' or 'demand'

if (!$gameId) {
    Response::error('game_id is required.');
}

$capTotal = CapacityDriver::totalCostSharePercent($gameId);
$demandTotal = DemandDriver::totalDemandSharePercent($gameId);
$combinedTotal = round($capTotal + $demandTotal, 2);
$balanced = abs($combinedTotal - 100.00) < 0.01;

Response::success('Total calculated.', [
    'capacity_total' => round($capTotal, 2),
    'demand_total' => round($demandTotal, 2),
    'combined_total' => $combinedTotal,
    'balanced' => $balanced,
    'difference' => round(100.00 - $combinedTotal, 2),
]);
