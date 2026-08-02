<?php
/**
 * MCQG Engine - Validate Investment Mapping
 * Path: engine/investment/validate_investment_mapping.php
 *
 * Source: MG19 Slide 6 - each investment has Min/Max investment
 * value and an Increment; a team's chosen investment amount must
 * respect all three. Used by ajax/player_ajax/save_investment_selection.php
 * before it's allowed to save.
 */

function validateInvestmentAmount(float $chosenValue, float $minValue, float $maxValue, float $increment): array
{
    $errors = [];

    if ($chosenValue < $minValue) {
        $errors[] = "Amount cannot be less than the minimum investment ({$minValue}).";
    }
    if ($chosenValue > $maxValue) {
        $errors[] = "Amount cannot exceed the maximum investment ({$maxValue}).";
    }
    if ($increment > 0) {
        $stepsFromMin = ($chosenValue - $minValue) / $increment;
        if (abs($stepsFromMin - round($stepsFromMin)) > 0.0001) {
            $errors[] = "Amount must move in steps of {$increment} from the minimum value.";
        }
    }

    return ['valid' => empty($errors), 'errors' => $errors];
}

/**
 * Confirms a team hasn't exceeded an investment's purchase_limit
 * across the rounds played so far (MG19 Slide 6: repeat_allowed / purchase_limit).
 */
function validatePurchaseLimit(int $timesAlreadyPurchased, bool $repeatAllowed, int $purchaseLimit): array
{
    if (!$repeatAllowed && $timesAlreadyPurchased >= 1) {
        return ['valid' => false, 'errors' => ['This investment can only be purchased once.']];
    }
    if ($timesAlreadyPurchased >= $purchaseLimit) {
        return ['valid' => false, 'errors' => ["Purchase limit of {$purchaseLimit} reached for this investment."]];
    }
    return ['valid' => true, 'errors' => []];
}
