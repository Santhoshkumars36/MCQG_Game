<?php
/**
 * MCQG Engine - Apply Driver Effect
 * Path: engine/cost/apply_driver_effect.php
 *
 * Source: MG19 Slide 12 - "A team's investment share in a Capacity
 * Driver reduces the linked cost driver proportionally. Example:
 * a 20% investment share in 'Quality' reduces the linked 'Cost of
 * errors' driver from 15% to 12%."
 *
 * Source: Doc 2 Entity 5 (investment_effect) - effect_type can be
 * Increase, Decrease, Set, or Multiplier.
 */

function applyDriverEffect(
    float $baseDriverValue,
    string $effectType,
    float $effectPercent,
    float $investedSharePercent
): float {
    // The actual applied effect scales with how much of the invesment's
    // min-max range the team chose to invest (investedSharePercent 0-100).
    $scaledEffect = $effectPercent * ($investedSharePercent / 100);

    switch ($effectType) {
        case 'Increase':
            return round($baseDriverValue * (1 + abs($scaledEffect) / 100), 4);

        case 'Decrease':
            return round($baseDriverValue * (1 - abs($scaledEffect) / 100), 4);

        case 'Set':
            return round($scaledEffect, 4);

        case 'Multiplier':
            return round($baseDriverValue * $scaledEffect, 4);

        default:
            return $baseDriverValue;
    }
}

/**
 * Apply ALL investment effects a team purchased to ALL of their
 * capacity drivers for one round. Returns the adjusted driver set,
 * ready for calculateTeamUnitCost().
 *
 * $capacityDriverCosts: output of calculateCapacityDriverCosts()
 * $teamInvestmentEffects: rows joining team_investment_selection -> investment_effect
 *     each: ['driver_id','effect_type','effect_value' (percent),'invested_percent']
 */
function applyAllDriverEffects(array $capacityDriverCosts, array $teamInvestmentEffects): array
{
    foreach ($teamInvestmentEffects as $effect) {
        $driverId = $effect['driver_id'];
        if (!isset($capacityDriverCosts[$driverId])) {
            continue; // effect targets a demand driver, not a cost driver - skip here
        }

        $capacityDriverCosts[$driverId]['cost_value'] = applyDriverEffect(
            $capacityDriverCosts[$driverId]['cost_value'],
            $effect['effect_type'],
            (float) $effect['effect_value'],
            (float) $effect['invested_percent']
        );
    }

    return $capacityDriverCosts;
}
