<?php
/**
 * MCQG - Team Model
 * Path: classes/Team.php
 * Wraps the team_master table. Closes the multiplayer/team-setup
 * gap identified during requirement analysis (no source document
 * defined a team model, so this was added).
 */

class Team
{
    public static function find(int $teamId): array|false
    {
        return Database::getInstance()->fetchOne("SELECT * FROM team_master WHERE team_id = :t", ['t' => $teamId]);
    }

    public static function findByUsername(string $username): array|false
    {
        return Database::getInstance()->fetchOne("SELECT * FROM team_master WHERE username = :u", ['u' => $username]);
    }

    public static function forGame(int $gameId, bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM team_master WHERE game_id = :g";
        $params = ['g' => $gameId];
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY team_name";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    public static function create(int $gameId, string $teamName, string $username, string $password, int $openingInventory, float $openingBudget): string
    {
        return Database::getInstance()->insert('team_master', [
            'game_id' => $gameId,
            'team_name' => $teamName,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'opening_inventory' => $openingInventory,
            'opening_budget' => $openingBudget,
            'is_active' => 1,
        ]);
    }

    public static function update(int $teamId, array $data): int
    {
        return Database::getInstance()->update('team_master', $data, 'team_id = :t', ['t' => $teamId]);
    }

    public static function resetLogin(int $teamId, string $username, string $newPassword): int
    {
        return self::update($teamId, [
            'username' => $username,
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);
    }

    public static function usernameTaken(string $username, ?int $excludeTeamId = null): bool
    {
        $db = Database::getInstance();
        if ($excludeTeamId) {
            $row = $db->fetchOne(
                "SELECT team_id FROM team_master WHERE username = :u AND team_id != :t",
                ['u' => $username, 't' => $excludeTeamId]
            );
        } else {
            $row = $db->fetchOne("SELECT team_id FROM team_master WHERE username = :u", ['u' => $username]);
        }
        return (bool) $row;
    }

    public static function currentCashPosition(int $teamId): float
    {
        $db = Database::getInstance();
        $latest = $db->fetchOne(
            "SELECT cash_position FROM team_result WHERE team_id = :t ORDER BY year_no DESC LIMIT 1",
            ['t' => $teamId]
        );
        if ($latest) {
            return (float) $latest['cash_position'];
        }
        $team = self::find($teamId);
        return $team ? (float) $team['opening_budget'] : 0.0;
    }
}
