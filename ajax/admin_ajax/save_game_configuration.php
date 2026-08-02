<?php
/**
 * MCQG AJAX (Admin) - Save Game Configuration
 * Path: ajax/admin_ajax/save_game_configuration.php
 * Source: Doc 2 Entity 6 / MG19 Slide 7 - generic key-value rule
 * store (Capacity, Pricing, Market Allocation, Dashboard, Winning
 * Criteria categories) - new rules never need a schema change.
 */
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../classes/GameConfiguration.php';
Auth::requireAdmin();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$gameId = (int) ($input['game_id'] ?? 0);
$category = trim($input['configuration_category'] ?? '');
$key = trim($input['configuration_key'] ?? '');
$value = trim($input['configuration_value'] ?? '');
$valueType = $input['value_type'] ?? 'Text';
$description = trim($input['description'] ?? '');

if (!$gameId || !Validator::required($category) || !Validator::required($key)) {
    Response::error('Category and key are both required.');
}

GameConfiguration::set($gameId, $category, $key, $value, $valueType, $description);

Logger::activity("Game configuration '{$key}' saved for game {$gameId}.");
Response::success('Configuration saved.', ['key' => $key, 'value' => $value]);
