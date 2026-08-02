<?php
/**
 * MCQG AJAX (Admin) - Save Capacity Driver
 * Path: ajax/admin_ajax/save_capacity_driver.php
 * Source: MG19 Slide 5 - inline add/edit of a Capacity Driver row
 * without a full page reload.
 */
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../classes/CapacityDriver.php';
Auth::requireAdmin();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$gameId = (int) ($input['game_id'] ?? 0);
$driverId = (int) ($input['driver_id'] ?? 0);
$groupName = trim($input['group_name'] ?? '');
$driverName = trim($input['driver_name'] ?? '');
$costSharePercent = (float) ($input['cost_share_percent'] ?? 0);

if (!$gameId || !Validator::required($groupName) || !Validator::required($driverName)) {
    Response::error('Group name and driver name are required.');
}
if (!Validator::inRange($costSharePercent, 0, 100)) {
    Response::error('Cost share % must be between 0 and 100.');
}

if ($driverId) {
    CapacityDriver::update($driverId, [
        'group_name' => $groupName, 'driver_name' => $driverName, 'cost_share_percent' => $costSharePercent,
    ]);
} else {
    $driverId = CapacityDriver::create($gameId, $groupName, $driverName, $costSharePercent);
}

Logger::activity("Capacity driver '{$driverName}' saved for game {$gameId}.");

Response::success('Capacity driver saved.', [
    'driver_id' => $driverId,
    'total_percent' => round(CapacityDriver::totalCostSharePercent($gameId), 2),
    'balanced' => CapacityDriver::isBalanced($gameId),
]);
