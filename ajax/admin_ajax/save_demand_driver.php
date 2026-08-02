<?php
/**
 * MCQG AJAX (Admin) - Save Demand Driver
 * Path: ajax/admin_ajax/save_demand_driver.php
 * Source: MG19 Slide 5 - inline add/edit of a Demand Driver row.
 */
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../classes/DemandDriver.php';
Auth::requireAdmin();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$gameId = (int) ($input['game_id'] ?? 0);
$driverId = (int) ($input['driver_id'] ?? 0);
$groupName = trim($input['group_name'] ?? '');
$driverName = trim($input['driver_name'] ?? '');
$demandSharePercent = (float) ($input['demand_share_percent'] ?? 0);

if (!$gameId || !Validator::required($groupName) || !Validator::required($driverName)) {
    Response::error('Group name and driver name are required.');
}
if (!Validator::inRange($demandSharePercent, 0, 100)) {
    Response::error('Demand share % must be between 0 and 100.');
}

if ($driverId) {
    DemandDriver::update($driverId, [
        'group_name' => $groupName, 'driver_name' => $driverName, 'demand_share_percent' => $demandSharePercent,
    ]);
} else {
    $driverId = DemandDriver::create($gameId, $groupName, $driverName, $demandSharePercent);
}

Logger::activity("Demand driver '{$driverName}' saved for game {$gameId}.");

Response::success('Demand driver saved.', [
    'driver_id' => $driverId,
    'total_percent' => round(DemandDriver::totalDemandSharePercent($gameId), 2),
    'balanced' => DemandDriver::isBalanced($gameId),
]);
