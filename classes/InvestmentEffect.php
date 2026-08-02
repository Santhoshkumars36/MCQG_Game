<?php
/**
 * MCQG - Investment Effect Model
 * Path: classes/InvestmentEffect.php
 * Wraps the investment_effect table - the many-to-many link between
 * investments and the Capacity/Demand Drivers they change
 * (Doc 2 Entity 5 / MG19 Slide 6).
 */

class InvestmentEffect
{
    public static function forInvestment(int $investmentId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM investment_effect WHERE investment_id = :i",
            ['i' => $investmentId]
        );
    }

    public static function forDriver(string $driverType, int $driverId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM investment_effect WHERE driver_type = :dt AND driver_id = :d",
            ['dt' => $driverType, 'd' => $driverId]
        );
    }

    public static function create(int $investmentId, string $driverType, int $driverId, float $minPercent, float $maxPercent, float $incrementPercent, string $injectMessage = ''): string
    {
        return Database::getInstance()->insert('investment_effect', [
            'investment_id' => $investmentId,
            'driver_type' => $driverType,
            'driver_id' => $driverId,
            'min_percent' => $minPercent,
            'max_percent' => $maxPercent,
            'increment_percent' => $incrementPercent,
            'inject_message' => $injectMessage,
        ]);
    }

    public static function update(int $effectId, array $data): int
    {
        return Database::getInstance()->update('investment_effect', $data, 'effect_id = :e', ['e' => $effectId]);
    }

    public static function delete(int $effectId): int
    {
        return Database::getInstance()->delete('investment_effect', 'effect_id = :e', ['e' => $effectId]);
    }

    /**
     * MG19 Slide 6: Min%/Max% are shown green if positive, red if
     * negative - this helper hands back a ready-to-use CSS class.
     */
    public static function colorClassFor(float $percent): string
    {
        return $percent >= 0 ? 'effect-positive' : 'effect-negative';
    }

    /** Auto-generates the "inject message" the admin does not type manually (MG19 Slide 6) */
    public static function generateInjectMessage(string $investmentName, string $driverName, float $minPercent, float $maxPercent): string
    {
        $direction = $maxPercent >= 0 ? 'improves' : 'reduces';
        return "{$investmentName} {$direction} {$driverName} by {$minPercent}% to {$maxPercent}% depending on investment level.";
    }
}
