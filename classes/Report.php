<?php
/**
 * MCQG - Report Model
 * Path: classes/Report.php
 * Wraps team_result and produces the summarized data structures
 * used by admin/reports/* and player/results/* (MG19 Slides 12-13,
 * Doc 1 "Winning the Competition").
 */

class Report
{
    public static function roundResults(int $gameId, int $yearNo): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT r.*, t.team_name FROM team_result r
             INNER JOIN team_master t ON t.team_id = r.team_id
             WHERE r.game_id = :g AND r.year_no = :y ORDER BY r.operating_profit DESC",
            ['g' => $gameId, 'y' => $yearNo]
        );
    }

    public static function teamResultForYear(int $teamId, int $yearNo): array|false
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM team_result WHERE team_id = :t AND year_no = :y",
            ['t' => $teamId, 'y' => $yearNo]
        );
    }

    /**
     * Cumulative standings across all processed rounds, ranked by
     * total operating profit (Doc 1's default winning basis).
     * Returns an array already sorted best-to-worst.
     */
    public static function cumulativeStandings(int $gameId): array
    {
        $db = Database::getInstance();
        $teams = $db->fetchAll("SELECT team_id, team_name FROM team_master WHERE game_id = :g", ['g' => $gameId]);

        $standings = [];
        foreach ($teams as $t) {
            $total = $db->fetchOne(
                "SELECT COALESCE(SUM(operating_profit), 0) AS total FROM team_result WHERE team_id = :t",
                ['t' => $t['team_id']]
            );
            $standings[] = [
                'team_id' => $t['team_id'],
                'team_name' => $t['team_name'],
                'cumulative_profit' => (float) $total['total'],
            ];
        }

        usort($standings, fn($a, $b) => $b['cumulative_profit'] <=> $a['cumulative_profit']);
        return $standings;
    }

    public static function rankOf(int $gameId, int $teamId): ?int
    {
        $standings = self::cumulativeStandings($gameId);
        foreach ($standings as $i => $s) {
            if ($s['team_id'] == $teamId) {
                return $i + 1;
            }
        }
        return null;
    }

    /** Investment matrix data - MG19 Slide 13's competitive-intelligence view */
    public static function investmentMatrix(int $gameId, int $yearNo): array
    {
        $db = Database::getInstance();
        $investments = $db->fetchAll(
            "SELECT investment_id, investment_name FROM investment_master WHERE game_id = :g ORDER BY display_order",
            ['g' => $gameId]
        );
        $teams = $db->fetchAll("SELECT team_id, team_name FROM team_master WHERE game_id = :g", ['g' => $gameId]);

        $matrix = [];
        foreach ($teams as $t) {
            $decision = $db->fetchOne(
                "SELECT decision_id FROM team_decision WHERE team_id = :t AND year_no = :y AND status = 'Submitted'",
                ['t' => $t['team_id'], 'y' => $yearNo]
            );
            $shares = [];
            foreach ($investments as $inv) {
                if (!$decision) { $shares[] = 0.0; continue; }
                $sel = $db->fetchOne(
                    "SELECT invested_percent FROM team_investment_selection WHERE decision_id = :d AND investment_id = :i",
                    ['d' => $decision['decision_id'], 'i' => $inv['investment_id']]
                );
                $shares[] = $sel ? (float) $sel['invested_percent'] : 0.0;
            }
            $matrix[] = ['team_name' => $t['team_name'], 'shares' => $shares];
        }

        return ['drivers' => array_column($investments, 'investment_name'), 'teams' => $matrix];
    }
}
