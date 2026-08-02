<?php
/**
 * =====================================================================
 * MCQG - core/Validator.php
 * Purpose: Every validation rule described across the requirement
 *          documents, in one reusable place, so both the AJAX
 *          endpoints (live, as-you-type validation) and the final
 *          server-side form submission use the EXACT same rules -
 *          no duplicated/conflicting logic between JS and PHP.
 *
 * Key rules implemented here (traced back to source):
 *   - percentageTotalsEqual100()  -> Doc 3, Slides 5 & 7
 *   - inRange()                    -> Doc 3, Slide 7 (price min/max),
 *                                     Slide 4 (capacity min/max)
 *   - isValidIncrement()           -> Doc 3, Slides 4, 5, 6, 11
 *   - csrfTokenValid()             -> security hardening (not in ARD,
 *                                     added as standard best practice)
 * =====================================================================
 */

if (!defined('MCQG_APP')) {
    die('Direct access not permitted.');
}

class Validator
{
    /** @var string[] Accumulates human-readable error messages */
    private array $errors = [];

    public function required($value, string $fieldLabel): self
    {
        if ($value === null || trim((string)$value) === '') {
            $this->errors[] = "$fieldLabel is required.";
        }
        return $this;
    }

    public function numeric($value, string $fieldLabel): self
    {
        if (!is_numeric($value)) {
            $this->errors[] = "$fieldLabel must be a number.";
        }
        return $this;
    }

    public function inRange($value, float $min, float $max, string $fieldLabel): self
    {
        if (!is_numeric($value) || $value < $min || $value > $max) {
            $this->errors[] = "$fieldLabel must be between $min and $max.";
        }
        return $this;
    }

    /**
     * Checks a value lands exactly on an allowed increment step from a
     * base minimum - e.g. Capacity Increment = 100 means only 4000,
     * 4100, 4200... are valid (Doc 3, Slide 4/6/11).
     */
    public function isValidIncrement($value, float $min, float $increment, string $fieldLabel): self
    {
        if ($increment <= 0) {
            return $this; // no increment restriction configured
        }
        $steps = ($value - $min) / $increment;
        if (abs($steps - round($steps)) > 0.0001) {
            $this->errors[] = "$fieldLabel must move in steps of $increment starting from $min.";
        }
        return $this;
    }

    public function maxDecimalPlaces($value, int $decimals, string $fieldLabel): self
    {
        $decimalPart = explode('.', (string)$value)[1] ?? '';
        if (strlen($decimalPart) > $decimals) {
            $this->errors[] = "$fieldLabel can have at most $decimals decimal place(s).";
        }
        return $this;
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
        return abs($sum - DRIVER_SHARE_TOTAL_REQUIRED) <= $tolerance;
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
