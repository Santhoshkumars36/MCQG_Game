<?php
/**
 * MCQG - Round Model
 * Path: classes/Round.php
 * Wraps game_round_status + team_decision - the round lifecycle
 * (Open -> AllSubmitted -> Processed) described in MG19 Slide 15.
 */

class Round
{
    public static function forGame(int $gameId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM game_round_status WHERE game_id = :g ORDER BY year_no",
            ['g' => $gameId]
        );
    }

    public static function find(int $gameId, int $yearNo): array|false
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM game_round_status WHERE game_id = :g AND year_no = :y",
            ['g' => $gameId, 'y' => $yearNo]
        );
    }

    /** The earliest round that isn't Processed yet, for admin round-control screens */
    public static function currentForGame(int $gameId): array|false
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM game_round_status WHERE game_id = :g AND status != :p ORDER BY year_no LIMIT 1",
            ['g' => $gameId, 'p' => ROUND_STATUS_PROCESSED]
        );
    }

    /** The earliest round a specific team still needs to submit for */
    public static function currentForTeam(int $gameId, int $teamId): array|false
    {
        return Database::getInstance()->fetchOne(
            "SELECT grs.* FROM game_round_status grs
             WHERE grs.game_id = :g AND grs.status != :p
             AND NOT EXISTS (
                 SELECT 1 FROM team_decision td
                 WHERE td.team_id = :t AND td.year_no = grs.year_no AND td.status = 'Submitted'
             )
             ORDER BY grs.year_no LIMIT 1",
            ['g' => $gameId, 'p' => ROUND_STATUS_PROCESSED, 't' => $teamId]
        );
    }

    public static function submissionCount(int $gameId, int $yearNo): array
    {
        $db = Database::getInstance();
        $totalTeams = (int) $db->fetchOne(
            "SELECT COUNT(*) AS c FROM team_master WHERE game_id = :g AND is_active = 1",
            ['g' => $gameId]
        )['c'];
        $submitted = (int) $db->fetchOne(
            "SELECT COUNT(*) AS c FROM team_decision WHERE game_id = :g AND year_no = :y AND status = 'Submitted'",
            ['g' => $gameId, 'y' => $yearNo]
        )['c'];

        return ['submitted' => $submitted, 'total' => $totalTeams, 'all_submitted' => $totalTeams > 0 && $submitted === $totalTeams];
    }

    public static function markProcessed(int $gameId, int $yearNo, int $adminId): int
    {
        return Database::getInstance()->update(
            'game_round_status',
            ['status' => ROUND_STATUS_PROCESSED, 'processed_on' => date('Y-m-d H:i:s'), 'processed_by' => $adminId],
            'game_id = :g AND year_no = :y',
            ['g' => $gameId, 'y' => $yearNo]
        );
    }

    // --- Team Decision helpers -------------------------------------------

    public static function decisionFor(int $teamId, int $yearNo): array|false
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM team_decision WHERE team_id = :t AND year_no = :y",
            ['t' => $teamId, 'y' => $yearNo]
        );
    }

    public static function saveDraftDecision(int $teamId, int $gameId, int $yearNo, array $data): string
    {
        $db = Database::getInstance();
        $existing = self::decisionFor($teamId, $yearNo);

        if ($existing) {
            $db->update('team_decision', $data, 'decision_id = :d', ['d' => $existing['decision_id']]);
            return (string) $existing['decision_id'];
        }

        return $db->insert('team_decision', array_merge($data, [
            'team_id' => $teamId, 'game_id' => $gameId, 'year_no' => $yearNo, 'status' => 'Draft',
        ]));
    }

    public static function submitDecision(int $decisionId): int
    {
        return Database::getInstance()->update(
            'team_decision',
            ['status' => 'Submitted', 'submitted_on' => date('Y-m-d H:i:s')],
            'decision_id = :d',
            ['d' => $decisionId]
        );
    }
}
