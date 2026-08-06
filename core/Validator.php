<?php
/**
 * =====================================================================
 * MCQG - core/Validator.php
 * Purpose: Every validation rule described across the requirement
 *          documents, in one reusable place, so both the AJAX
 *          endpoints (live, as-you-type validation) and the final
 *          server-side form submission use the EXACT same rules -
 *          no duplicated/conflicting logic between JS and PHP.
 * =====================================================================
 */

if (!defined('MCQG_APP')) {
    require_once __DIR__ . '/../config/constants.php';
}

class Validator
{
    /** @var string[] Accumulates human-readable error messages */
    private array $errors = [];

    public static function required($value, ?string $fieldLabel = null): bool
    {
        return ($value !== null && trim((string)$value) !== '');
    }

    public static function numeric($value): bool
    {
        return is_numeric($value);
    }

    public static function inRange($value, float $min, float $max): bool
    {
        return is_numeric($value) && $value >= $min && $value <= $max;
    }

    public static function isValidIncrement($value, float $min, float $increment): bool
    {
        if ($increment <= 0) {
            return true;
        }
        $steps = ($value - $min) / $increment;
        return abs($steps - round($steps)) <= 0.0001;
    }

    public static function maxDecimalPlaces($value, int $decimals): bool
    {
        $decimalPart = explode('.', (string)$value)[1] ?? '';
        return strlen($decimalPart) <= $decimals;
    }

    public static function isValidEffectPercent($value): bool
    {
        return is_numeric($value) && $value >= -100.0 && $value <= 100.0;
    }

    public static function priceWithinRange(float $price, float $minPrice, float $maxPrice): bool
    {
        return $price >= $minPrice && $price <= $maxPrice;
    }

    public static function percentTotalsTo100(array $percentages, float $tolerance = 0.01): bool
    {
        return self::percentageTotalsEqual100($percentages, $tolerance);
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    // -------------------------------------------------------------
    // STATIC, STANDALONE RULES (used directly without instantiating)
    // -------------------------------------------------------------

    /**
     * THE key validation rule from Doc 3, Slides 5 & 7:
     * "the sum total of this % should be 100%, please validate on saving"
     * Used for both Capacity Driver cost_share_percent and
     * Demand Driver demand_share_percent.
     *
     * @param float[] $percentages
     */
    public static function percentageTotalsEqual100(array $percentages, float $tolerance = 0.01): bool
    {
        $sum = array_sum($percentages);
        $required = defined('DRIVER_SHARE_TOTAL_REQUIRED') ? DRIVER_SHARE_TOTAL_REQUIRED : 100.0;
        return abs($sum - $required) <= $tolerance;
    }

    /** Returns the actual total, so the UI can show "Currently: 92%" style messages. */
    public static function sumPercentages(array $percentages): float
    {
        return round(array_sum($percentages), 2);
    }

    /**
     * Doc 3, Slide 7: Sale price must fall within Min/Max, adjusted
     * by the Sales Price Tolerance % configured by the admin.
     */
    public static function priceWithinTolerance(
        float $enteredPrice,
        float $minPrice,
        float $maxPrice,
        float $tolerancePercent
    ): bool {
        $toleranceAmount = $maxPrice * ($tolerancePercent / 100);
        $effectiveMin = $minPrice - $toleranceAmount;
        $effectiveMax = $maxPrice + $toleranceAmount;
        return $enteredPrice >= $effectiveMin && $enteredPrice <= $effectiveMax;
    }

    /** Verifies the CSRF token submitted by a form matches the session token. */
    public static function csrfTokenValid(?string $submittedToken): bool
    {
        return is_string($submittedToken)
            && hash_equals(Session::csrfToken(), $submittedToken);
    }
}

