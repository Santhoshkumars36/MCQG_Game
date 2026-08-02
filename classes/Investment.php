<?php
/**
 * MCQG - Investment Model
 * Path: classes/Investment.php
 * Wraps the investment_master table (Doc 2 Entity 4 / MG19 Slide 6).
 */

class Investment
{
    public static function forGame(int $gameId, bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM investment_master WHERE game_id = :g";
        $params = ['g' => $gameId];
        if ($activeOnly) {
            $sql .= " AND active = 1";
        }
        $sql .= " ORDER BY display_order";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    public static function find(int $investmentId): array|false
    {
        return Database::getInstance()->fetchOne("SELECT * FROM investment_master WHERE investment_id = :i", ['i' => $investmentId]);
    }

    public static function create(array $data): string
    {
        $data['active'] = $data['active'] ?? 1;
        $data['display_order'] = $data['display_order'] ?? 0;
        return Database::getInstance()->insert('investment_master', $data);
    }

    public static function update(int $investmentId, array $data): int
    {
        return Database::getInstance()->update('investment_master', $data, 'investment_id = :i', ['i' => $investmentId]);
    }

    public static function deactivate(int $investmentId): int
    {
        return self::update($investmentId, ['active' => 0]);
    }

    /** MG19 Slide 6: how many times a team has already purchased this investment */
    public static function timesPurchasedByTeam(int $investmentId, int $teamId): int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) AS c FROM team_investment_selection tis
             INNER JOIN team_decision td ON td.decision_id = tis.decision_id
             WHERE tis.investment_id = :i AND td.team_id = :t",
            ['i' => $investmentId, 't' => $teamId]
        );
        return (int) ($row['c'] ?? 0);
    }

    public static function effects(int $investmentId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM investment_effect WHERE investment_id = :i",
            ['i' => $investmentId]
        );
    }
}
