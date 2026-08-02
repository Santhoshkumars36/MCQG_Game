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

if ($driverType === 'demand') {
    $total = DemandDriver::totalDemandSharePercent($gameId);
    $balanced = DemandDriver::isBalanced($gameId);
} else {
    $total = CapacityDriver::totalCostSharePercent($gameId);
    $balanced = CapacityDriver::isBalanced($gameId);
}

Response::success('Total calculated.', [
    'total' => round($total, 2),
    'balanced' => $balanced,
    'difference' => round(PERCENT_TOTAL_REQUIRED - $total, 2),
]);
