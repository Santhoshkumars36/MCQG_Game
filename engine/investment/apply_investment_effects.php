<?php
/**
 * MCQG Engine - Apply Investment Effects (Orchestrator)
 * Path: engine/investment/apply_investment_effects.php
 *
 * Source: Doc 2 "Design Philosophy" - engine logic reduced to
 * 3 steps: (1) Read Financial Parameters, (2) Apply Investment
 * Effects, (3) Execute calculations using Game Configuration rules.
 * This file is Step 2 of that 3-step engine.
 */

require_once __DIR__ . '/../cost/apply_driver_effect.php';

/**
 * Pulls every investment_effect row linked to the investments a
 * team selected this round, joined with how much of the min-max
 * range they invested (team_investment_selection.invested_percent).
 *
 * Returns a flat list ready for applyAllDriverEffects() /
 * demand-driver allocation.
 */
function getTeamInvestmentEffects(int $decisionId): array
{
    $db = Database::getInstance();

    $sql = "SELECT ie.driver_type, ie.driver_id, ie.effect_type_placeholder,
                   ie.min_percent, ie.max_percent, tis.invested_percent,
                   tis.invested_value, ie.investment_id
            FROM team_investment_selection tis
            INNER JOIN investment_effect ie ON ie.investment_id = tis.investment_id
            WHERE tis.decision_id = :decision_id";

    // NOTE: investment_effect table stores min_percent/max_percent as the
    // configured range, not a single 'effect_value'. The team's chosen
    // invested_percent (0-100 of that range) determines the actual effect
    // applied - resolved below.
    $rows = $db->fetchAll($sql, ['decision_id' => $decisionId]);

    $effects = [];
    foreach ($rows as $row) {
        $range = (float) $row['max_percent'] - (float) $row['min_percent'];
        $actualEffectPercent = (float) $row['min_percent'] + ($range * ((float) $row['invested_percent'] / 100));

        $effects[] = [
            'driver_type'      => $row['driver_type'],   // 'Capacity' or 'Demand'
            'driver_id'        => $row['driver_id'],
            'effect_type'      => (float) $actualEffectPercent >= 0 ? 'Increase' : 'Decrease',
            'effect_value'     => abs($actualEffectPercent),
            'invested_percent' => 100, // already resolved into effect_value above
            'invested_value'   => (float) $row['invested_value'],
        ];
    }

    return $effects;
}

/**
 * Splits a flat effects list into capacity-driver effects and
 * demand-driver effects, so each can feed its own engine step.
 */
function splitEffectsByDriverType(array $effects): array
{
    $capacity = array_values(array_filter($effects, fn($e) => $e['driver_type'] === DRIVER_TYPE_CAPACITY));
    $demand   = array_values(array_filter($effects, fn($e) => $e['driver_type'] === DRIVER_TYPE_DEMAND));

    return ['capacity' => $capacity, 'demand' => $demand];
}
