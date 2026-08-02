<?php
/**
 * MCQG - Game Configuration Model
 * Path: classes/GameConfiguration.php
 * Wraps game_configuration (generic key-value rules, Doc 2 Entity 6)
 * and game_demand_allocation (per-period allocation rules, MG19 Slide 7).
 */

class GameConfiguration
{
    public static function forGame(int $gameId, ?string $category = null): array
    {
        $sql = "SELECT * FROM game_configuration WHERE game_id = :g";
        $params = ['g' => $gameId];
        if ($category) {
            $sql .= " AND configuration_category = :c";
            $params['c'] = $category;
        }
        return Database::getInstance()->fetchAll($sql, $params);
    }

    public static function get(int $gameId, string $key, $default = null)
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT configuration_value FROM game_configuration WHERE game_id = :g AND configuration_key = :k",
            ['g' => $gameId, 'k' => $key]
        );
        return $row ? $row['configuration_value'] : $default;
    }

    public static function set(int $gameId, string $category, string $key, string $value, string $valueType = 'Text', string $description = ''): void
    {
        $db = Database::getInstance();
        $existing = $db->fetchOne(
            "SELECT configuration_id FROM game_configuration WHERE game_id = :g AND configuration_key = :k",
            ['g' => $gameId, 'k' => $key]
        );
        $data = ['configuration_category' => $category, 'configuration_value' => $value, 'value_type' => $valueType, 'description' => $description];

        if ($existing) {
            $db->update('game_configuration', $data, 'configuration_id = :id', ['id' => $existing['configuration_id']]);
        } else {
            $db->insert('game_configuration', array_merge($data, ['game_id' => $gameId, 'configuration_key' => $key]));
        }
    }

    /** MG19 Slide 7: whether the results dashboard shows a given field to teams */
    public static function isDashboardVisible(int $gameId, string $field): bool
    {
        $value = self::get($gameId, $field, 'No');
        return strtolower($value) === 'yes';
    }

    // --- Demand Allocation Logic (per period) ---------------------------

    public static function allocationRuleForYear(int $gameId, int $yearNo): array|false
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM game_demand_allocation WHERE game_id = :g AND year_no = :y",
            ['g' => $gameId, 'y' => $yearNo]
        );
    }

    public static function saveAllocationRule(int $gameId, int $yearNo, bool $priceTick, float $minPercent, float $demandDriverPercent): void
    {
        $db = Database::getInstance();
        $existing = self::allocationRuleForYear($gameId, $yearNo);
        $data = ['price_tick' => $priceTick ? 1 : 0, 'min_percent' => $minPercent, 'demand_driver_percent' => $demandDriverPercent];

        if ($existing) {
            $db->update('game_demand_allocation', $data, 'game_id = :g AND year_no = :y', ['g' => $gameId, 'y' => $yearNo]);
        } else {
            $db->insert('game_demand_allocation', array_merge($data, ['game_id' => $gameId, 'year_no' => $yearNo]));
        }
    }
}
