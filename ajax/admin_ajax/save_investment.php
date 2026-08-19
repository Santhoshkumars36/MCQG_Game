<?php
/**
 * MCQG AJAX (Admin) - Save Investment
 * Path: ajax/admin_ajax/save_investment.php
 * Source: MG19 Slide 6 - Name, Description, Min/Max investment
 * value, Increment, Effective From, Repeat Allowed, Purchase Limit.
 */
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../classes/Investment.php';
Auth::requireAdmin();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$gameId = (int) ($input['game_id'] ?? 0);
$investmentId = (int) ($input['investment_id'] ?? 0);

$name = trim($input['investment_name'] ?? '');
$description = trim($input['description'] ?? '');
$minValue = (float) ($input['min_investment_value'] ?? 0);
$maxValue = (float) ($input['max_investment_value'] ?? 0);
$increment = (float) ($input['increment_value'] ?? 1);
$effectiveFrom = $input['effective_from'] ?? 'Immediate';
$repeatAllowed = !empty($input['repeat_allowed']) ? 1 : 0;
$purchaseLimit = (int) ($input['purchase_limit'] ?? 1);

if (!$gameId || !Validator::required($name)) {
    Response::error('Investment name is required.');
}
if ($minValue > $maxValue) {
    Response::error('Minimum investment value cannot exceed the maximum.');
}

$data = [
    'investment_name' => $name,
    'description' => $description,
    'min_investment_value' => $minValue,
    'max_investment_value' => $maxValue,
    'increment_value' => $increment,
    'effective_from' => $effectiveFrom,
    'repeat_allowed' => $repeatAllowed,
    'purchase_limit' => $purchaseLimit,
];

if ($investmentId) {
    Investment::update($investmentId, $data);
} else {
    $investmentId = Investment::create(array_merge($data, ['game_id' => $gameId]));
}

Logger::activity("Investment '{$name}' saved for game {$gameId}.");
Response::success('Investment saved.', ['investment_id' => $investmentId]);
