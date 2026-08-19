<?php
/**
 * MCQG AJAX (Admin) - Save Investment Effect
 * Path: ajax/admin_ajax/save_investment_effect.php
 * Source: MG19 Slide 6 - maps an investment to a Capacity/Demand
 * Driver with Min%/Max%/Increment%, and auto-generates the
 * "inject message" (the admin never types it).
 */
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../classes/InvestmentEffect.php';
require_once __DIR__ . '/../../classes/Investment.php';
require_once __DIR__ . '/../../classes/CapacityDriver.php';
require_once __DIR__ . '/../../classes/DemandDriver.php';
Auth::requireAdmin();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$investmentId = (int) ($input['investment_id'] ?? 0);
$driverType = $input['driver_type'] ?? DRIVER_TYPE_CAPACITY;
$driverId = (int) ($input['driver_id'] ?? 0);
$effectDirection = ($input['effect_direction'] ?? 'Negative') === 'Positive' ? 'Positive' : 'Negative';
$val = ($effectDirection === 'Positive') ? 1.0 : -1.0;
$minPercent = $val;
$maxPercent = $val;
$incrementPercent = 0.0;

if (!$investmentId || !$driverId) {
    Response::error('Investment and driver are both required.');
}

$investment = Investment::find($investmentId);
$driver = $driverType === DRIVER_TYPE_DEMAND ? DemandDriver::find($driverId) : CapacityDriver::find($driverId);
$driverName = $driver['driver_name'] ?? 'the linked driver';

$invName = $investment['investment_name'] ?? 'This investment';
$injectMessage = "{$invName} " . ($effectDirection === 'Positive' ? 'increases' : 'reduces') . " {$driverName}.";

$effectId = InvestmentEffect::create($investmentId, $driverType, $driverId, $minPercent, $maxPercent, $incrementPercent, $injectMessage);

Logger::activity("Investment effect saved for investment {$investmentId} -> driver {$driverId}.");

Response::success('Investment effect saved.', [
    'effect_id' => $effectId,
    'inject_message' => $injectMessage,
    'min_color_class' => InvestmentEffect::colorClassFor($minPercent),
    'max_color_class' => InvestmentEffect::colorClassFor($maxPercent),
]);
