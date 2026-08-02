<?php
/**
 * MCQG - Demand Driver Model
 * Path: classes/DemandDriver.php
 * Wraps the demand_driver table (MG19 Slide 5 - "new section" added
 * alongside Capacity Drivers).
 */

class DemandDriver
{
    public static function forGame(int $gameId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM demand_driver WHERE game_id = :g ORDER BY group_name, driver_name",
            ['g' => $gameId]
        );
    }

    public static function find(int $driverId): array|false
    {
        return Database::getInstance()->fetchOne("SELECT * FROM demand_driver WHERE driver_id = :d", ['d' => $driverId]);
    }

    public static function create(int $gameId, string $groupName, string $driverName, float $demandSharePercent): string
    {
        return Database::getInstance()->insert('demand_driver', [
            'game_id' => $gameId,
            'group_name' => $groupName,
            'driver_name' => $driverName,
            'demand_share_percent' => $demandSharePercent,
        ]);
    }

    public static function update(int $driverId, array $data): int
    {
        return Database::getInstance()->update('demand_driver', $data, 'driver_id = :d', ['d' => $driverId]);
    }

    public static function delete(int $driverId): int
    {
        return Database::getInstance()->delete('demand_driver', 'driver_id = :d', ['d' => $driverId]);
    }

    /** MG19 Slide 5: Demand share % across all drivers for a game must total exactly 100% */
    public static function totalDemandSharePercent(int $gameId): float
    {
        $rows = self::forGame($gameId);
        return array_sum(array_map('floatval', array_column($rows, 'demand_share_percent')));
    }

    public static function isBalanced(int $gameId): bool
    {
        return abs(self::totalDemandSharePercent($gameId) - PERCENT_TOTAL_REQUIRED) < 0.01;
    }
}
