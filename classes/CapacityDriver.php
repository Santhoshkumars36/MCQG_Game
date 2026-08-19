<?php
/**
 * MCQG - Capacity Driver Model
 * Path: classes/CapacityDriver.php
 * Wraps the capacity_driver table (Doc 2 Entity 3 / MG19 Slide 5).
 */

class CapacityDriver
{
    public static function forGame(int $gameId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM capacity_driver WHERE game_id = :g ORDER BY group_name, driver_name",
            ['g' => $gameId]
        );
    }

    public static function find(int $driverId): array|false
    {
        return Database::getInstance()->fetchOne("SELECT * FROM capacity_driver WHERE driver_id = :d", ['d' => $driverId]);
    }

    public static function create(int $gameId, string $groupName, string $driverName, float $costSharePercent): string
    {
        return Database::getInstance()->insert('capacity_driver', [
            'game_id' => $gameId,
            'group_name' => $groupName,
            'driver_name' => $driverName,
            'cost_share_percent' => $costSharePercent,
        ]);
    }

    public static function update(int $driverId, array $data): int
    {
        return Database::getInstance()->update('capacity_driver', $data, 'driver_id = :d', ['d' => $driverId]);
    }

    public static function delete(int $driverId): int
    {
        return Database::getInstance()->delete('capacity_driver', 'driver_id = :d', ['d' => $driverId]);
    }

    /** MG19 Slide 8: Capacity driver cost share total */
    public static function totalCostSharePercent(int $gameId): float
    {
        $rows = self::forGame($gameId);
        return array_sum(array_map('floatval', array_column($rows, 'cost_share_percent')));
    }

    public static function getTotalCostSharePercent(int $gameId): float
    {
        return self::totalCostSharePercent($gameId);
    }

    public static function isBalanced(int $gameId): bool
    {
        $total = self::totalCostSharePercent($gameId);
        return $total > 0 && $total <= 100.00;
    }

    /** Recomputes and saves cost_value = capacity_cost x cost_share_percent for every driver in a game */
    public static function recalculateCostValues(int $gameId, float $capacityCost): void
    {
        $db = Database::getInstance();
        foreach (self::forGame($gameId) as $driver) {
            $costValue = $capacityCost * ((float) $driver['cost_share_percent'] / 100);
            $db->update('capacity_driver', ['cost_value' => round($costValue, 4)], 'driver_id = :d', ['d' => $driver['driver_id']]);
        }
    }
}
