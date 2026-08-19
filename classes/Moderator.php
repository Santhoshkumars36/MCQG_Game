<?php
/**
 * MCQG - classes/Moderator.php
 * Handles Moderator Control Center operations (MG19 Slides 12 & 13)
 */

if (!defined('MCQG_APP')) {
    require_once __DIR__ . '/../config/constants.php';
}

class Moderator
{
    /** Ensures schema tables exist for moderator functionality */
    public static function ensureTablesExist(): void
    {
        $db = Database::getInstance();
        
        // 1. Moderator Adjustments Table
        $db->execute("CREATE TABLE IF NOT EXISTS moderator_adjustment (
            adjustment_id INT AUTO_INCREMENT PRIMARY KEY,
            game_id INT NOT NULL,
            team_id INT NOT NULL,
            year_no INT NOT NULL,
            amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            reason VARCHAR(255) NOT NULL,
            created_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_mod_adj_game FOREIGN KEY (game_id) REFERENCES game_master(game_id) ON DELETE CASCADE,
            CONSTRAINT fk_mod_adj_team FOREIGN KEY (team_id) REFERENCES team_master(team_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 2. Message Center Table
        $db->execute("CREATE TABLE IF NOT EXISTS message_center (
            message_id INT AUTO_INCREMENT PRIMARY KEY,
            game_id INT NOT NULL,
            team_id INT NULL,
            year_no INT NOT NULL DEFAULT 1,
            sender_type ENUM('Moderator', 'System', 'Team') NOT NULL DEFAULT 'Moderator',
            sender_name VARCHAR(100) NOT NULL DEFAULT 'Moderator',
            message_text TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_msg_game FOREIGN KEY (game_id) REFERENCES game_master(game_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 3. Ensure launched_at column exists in game_round_status
        try {
            $db->execute("ALTER TABLE game_round_status ADD COLUMN launched_at DATETIME NULL");
        } catch (\Throwable $e) {}
    }

    /** Gets moderator dashboard data for a game */
    public static function getDashboardState(int $gameId): array
    {
        self::ensureTablesExist();
        $db = Database::getInstance();

        $game = $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]);
        if (!$game) return [];

        $years = (int) $game['no_of_years'];

        // Synchronize round status rows if missing
        for ($y = 1; $y <= $years; $y++) {
            $r = $db->fetchOne("SELECT * FROM game_round_status WHERE game_id = :g AND year_no = :y", ['g' => $gameId, 'y' => $y]);
            if (!$r) {
                $db->insert('game_round_status', [
                    'game_id' => $gameId,
                    'year_no' => $y,
                    'status' => ($y === 1) ? 'Not Launched' : 'Locked'
                ]);
            }
        }

        $rounds = $db->fetchAll("SELECT * FROM game_round_status WHERE game_id = :g ORDER BY year_no ASC", ['g' => $gameId]);
        $teams = $db->fetchAll("SELECT team_id, team_name, username, createdOn FROM team_master WHERE game_id = :g AND is_active = 1 ORDER BY team_name ASC", ['g' => $gameId]);

        // Find active round
        $activeYear = 1;
        foreach ($rounds as $r) {
            if ($r['status'] === 'Live' || $r['status'] === 'Open') {
                $activeYear = (int) $r['year_no'];
                break;
            } elseif ($r['status'] === 'Not Launched') {
                $activeYear = (int) $r['year_no'];
                break;
            } elseif ($r['status'] === 'Processed') {
                $activeYear = min($years, (int) $r['year_no'] + 1);
            }
        }

        // Live submission status for active round
        $teamStatuses = [];
        $submittedCount = 0;
        foreach ($teams as $t) {
            $d = $db->fetchOne("SELECT status, COALESCE(submitted_on, createdOn) AS last_action FROM team_decision WHERE team_id = :t AND year_no = :y", [
                't' => $t['team_id'],
                'y' => $activeYear
            ]);
            $st = $d['status'] ?? 'Not Started';
            if ($st === 'Submitted') $submittedCount++;

            $teamStatuses[] = [
                'team_id' => $t['team_id'],
                'team_name' => $t['team_name'],
                'status' => $st,
                'last_action' => $d['last_action'] ?? null
            ];
        }

        // Fetch recent messages
        $messages = $db->fetchAll("SELECT m.*, t.team_name FROM message_center m LEFT JOIN team_master t ON m.team_id = t.team_id WHERE m.game_id = :g ORDER BY m.created_on DESC LIMIT 20", ['g' => $gameId]);

        return [
            'game' => $game,
            'rounds' => $rounds,
            'teams' => $teams,
            'active_year' => $activeYear,
            'team_statuses' => $teamStatuses,
            'submitted_count' => $submittedCount,
            'total_teams' => count($teams),
            'messages' => $messages
        ];
    }

    /** Launches a round: unlocks for teams and initializes baseline data */
    public static function launchRound(int $gameId, int $yearNo, ?int $adminId = null): array
    {
        self::ensureTablesExist();
        $db = Database::getInstance();

        // Update status to Live
        $db->update('game_round_status', [
            'status' => 'Live',
            'launched_at' => date('Y-m-d H:i:s'),
            'processed_by' => $adminId
        ], 'game_id = :g AND year_no = :y', ['g' => $gameId, 'y' => $yearNo]);

        // Post system broadcast
        $db->insert('message_center', [
            'game_id' => $gameId,
            'team_id' => null,
            'year_no' => $yearNo,
            'sender_type' => 'System',
            'sender_name' => 'System Auto-trigger',
            'message_text' => "Round {$yearNo} is officially LAUNCHED! Teams can now submit decisions."
        ]);

        Logger::activity("Moderator launched Round {$yearNo} for Game #{$gameId}.");
        return ['success' => true, 'message' => "Round {$yearNo} launched successfully."];
    }

    /** Processes round results & propagates adjustments + market dynamics */
    public static function processRound(int $gameId, int $yearNo, ?int $adminId = null, bool $force = false): array
    {
        self::ensureTablesExist();
        $db = Database::getInstance();

        $teams = $db->fetchAll("SELECT team_id FROM team_master WHERE game_id = :g AND is_active = 1", ['g' => $gameId]);
        $submittedCount = 0;
        foreach ($teams as $t) {
            $d = $db->fetchOne("SELECT status FROM team_decision WHERE team_id = :t AND year_no = :y", ['t' => $t['team_id'], 'y' => $yearNo]);
            if (($d['status'] ?? '') === 'Submitted') $submittedCount++;
        }

        if (!$force && count($teams) > 0 && $submittedCount < count($teams)) {
            return [
                'success' => false,
                'unsubmitted' => true,
                'submitted_count' => $submittedCount,
                'total_teams' => count($teams),
                'message' => "Only {$submittedCount} of " . count($teams) . " teams have submitted. Are you sure you want to process results?"
            ];
        }

        // Set status to Processed
        $db->update('game_round_status', [
            'status' => 'Processed',
            'processed_on' => date('Y-m-d H:i:s'),
            'processed_by' => $adminId
        ], 'game_id = :g AND year_no = :y', ['g' => $gameId, 'y' => $yearNo]);

        // Unlock next round if exists
        $nextYear = $yearNo + 1;
        $nextRound = $db->fetchOne("SELECT * FROM game_round_status WHERE game_id = :g AND year_no = :y", ['g' => $gameId, 'y' => $nextYear]);
        if ($nextRound && $nextRound['status'] === 'Locked') {
            $db->update('game_round_status', ['status' => 'Not Launched'], 'game_id = :g AND year_no = :y', ['g' => $gameId, 'y' => $nextYear]);
        }

        // Propagate moderator adjustments to Message Center for next round
        $adjustments = $db->fetchAll("SELECT a.*, t.team_name FROM moderator_adjustment a JOIN team_master t ON a.team_id = t.team_id WHERE a.game_id = :g AND a.year_no = :y", ['g' => $gameId, 'y' => $nextYear]);
        foreach ($adjustments as $adj) {
            $sign = $adj['amount'] >= 0 ? '+' : '';
            $msg = "Moderator Adjustment for Round {$nextYear}: {$sign}" . number_format($adj['amount'], 2) . " (" . htmlspecialchars($adj['reason']) . ")";
            $db->insert('message_center', [
                'game_id' => $gameId,
                'team_id' => $adj['team_id'],
                'year_no' => $nextYear,
                'sender_type' => 'Moderator',
                'sender_name' => 'Moderator',
                'message_text' => $msg
            ]);
        }

        // Post round processed broadcast
        $db->insert('message_center', [
            'game_id' => $gameId,
            'team_id' => null,
            'year_no' => $yearNo,
            'sender_type' => 'System',
            'sender_name' => 'System Engine',
            'message_text' => "Round {$yearNo} results processed! Market dynamics and reports updated."
        ]);

        Logger::activity("Moderator processed Round {$yearNo} for Game #{$gameId}.");
        return ['success' => true, 'message' => "Round {$yearNo} results processed successfully."];
    }

    /** Saves a moderator adjustment (positive/negative + reason) */
    public static function saveAdjustment(int $gameId, int $teamId, int $yearNo, float $amount, string $reason): array
    {
        self::ensureTablesExist();
        $db = Database::getInstance();

        $db->insert('moderator_adjustment', [
            'game_id' => $gameId,
            'team_id' => $teamId,
            'year_no' => $yearNo,
            'amount' => $amount,
            'reason' => trim($reason)
        ]);

        $team = $db->fetchOne("SELECT team_name FROM team_master WHERE team_id = :t", ['t' => $teamId]);
        $teamName = $team['team_name'] ?? "Team #$teamId";

        $sign = $amount >= 0 ? '+' : '';
        $msgText = "Moderator adjustment of {$sign}" . number_format($amount, 2) . " assigned to {$teamName} for Round {$yearNo}. Reason: {$reason}";

        $db->insert('message_center', [
            'game_id' => $gameId,
            'team_id' => $teamId,
            'year_no' => $yearNo,
            'sender_type' => 'Moderator',
            'sender_name' => 'Moderator',
            'message_text' => $msgText
        ]);

        return ['success' => true, 'message' => 'Adjustment saved & posted to Message Center.'];
    }

    /** Fetches detailed team performance data for a selected round */
    public static function getTeamRoundDetail(int $gameId, int $teamId, int $yearNo): array
    {
        $db = Database::getInstance();
        $team = $db->fetchOne("SELECT * FROM team_master WHERE team_id = :t", ['t' => $teamId]);
        $decision = $db->fetchOne("SELECT * FROM team_decision WHERE team_id = :t AND year_no = :y", ['t' => $teamId, 'y' => $yearNo]);
        
        $investments = [];
        if ($decision) {
            $investments = $db->fetchAll("SELECT tis.*, im.investment_name, im.description FROM team_investment_selection tis JOIN investment_master im ON tis.investment_id = im.investment_id WHERE tis.decision_id = :d", ['d' => $decision['decision_id']]);
        }

        $result = $db->fetchOne("SELECT * FROM team_result WHERE team_id = :t AND year_no = :y", ['t' => $teamId, 'y' => $yearNo]);
        $adjustments = $db->fetchAll("SELECT * FROM moderator_adjustment WHERE team_id = :t AND year_no = :y", ['t' => $teamId, 'y' => $yearNo]);

        return [
            'team' => $team,
            'year_no' => $yearNo,
            'decision' => $decision,
            'investments' => $investments,
            'result' => $result,
            'adjustments' => $adjustments
        ];
    }

    /** Fetches overview for a specific round */
    public static function getRoundOverview(int $gameId, int $yearNo): array
    {
        $db = Database::getInstance();
        $round = $db->fetchOne("SELECT * FROM game_round_status WHERE game_id = :g AND year_no = :y", ['g' => $gameId, 'y' => $yearNo]);
        $teams = $db->fetchAll("SELECT team_id, team_name FROM team_master WHERE game_id = :g AND is_active = 1", ['g' => $gameId]);
        
        $teamStatuses = [];
        $submittedCount = 0;
        foreach ($teams as $t) {
            $d = $db->fetchOne("SELECT status, COALESCE(submitted_on, createdOn) AS last_action FROM team_decision WHERE team_id = :t AND year_no = :y", [
                't' => $t['team_id'],
                'y' => $yearNo
            ]);
            $res = $db->fetchOne("SELECT operating_profit, sales_revenue, demand_allocated FROM team_result WHERE team_id = :t AND year_no = :y", [
                't' => $t['team_id'],
                'y' => $yearNo
            ]);

            $st = $d['status'] ?? 'Not Started';
            if ($st === 'Submitted') $submittedCount++;

            $teamStatuses[] = [
                'team_id' => $t['team_id'],
                'team_name' => $t['team_name'],
                'status' => $st,
                'last_action' => $d['last_action'] ?? null,
                'operating_profit' => $res['operating_profit'] ?? null,
                'sales_revenue' => $res['sales_revenue'] ?? null,
                'demand_allocated' => $res['demand_allocated'] ?? null
            ];
        }

        return [
            'round' => $round,
            'year_no' => $yearNo,
            'submitted_count' => $submittedCount,
            'total_teams' => count($teams),
            'team_statuses' => $teamStatuses
        ];
    }
}
