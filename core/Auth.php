<?php
/**
 * =====================================================================
 * MCQG - core/Auth.php
 * Purpose: Handles login/logout and access-guarding for BOTH user
 *          types defined in the schema: admin_user and team_master.
 *          Used by admin/auth/login.php, player/auth/login.php, and
 *          includes/auth_check.php on every protected page.
 * =====================================================================
 */

if (!defined('MCQG_APP')) {
    die('Direct access not permitted.');
}

class Auth
{
    /**
     * Attempts an ADMIN login. Returns true on success.
     */
    public static function loginAdmin(string $username, string $password): bool
    {
        $db = Database::getInstance();
        $admin = $db->fetchOne(
            'SELECT admin_id, username, password_hash, full_name, is_active
             FROM admin_user WHERE username = :username LIMIT 1',
            [':username' => $username]
        );

        if (!$admin || (int)$admin['is_active'] !== 1) {
            Logger::warning("Failed admin login attempt for username: $username");
            return false;
        }

        if (!password_verify($password, $admin['password_hash'])) {
            Logger::warning("Wrong password for admin username: $username");
            return false;
        }

        // Regenerate session ID on every successful login (session fixation protection)
        session_regenerate_id(true);
        Session::set(SESSION_ADMIN_ID, (int)$admin['admin_id']);
        Session::set(SESSION_ADMIN_NAME, $admin['full_name'] ?: $admin['username']);
        Logger::activity("Admin '{$admin['username']}' logged in.");
        return true;
    }

    /**
     * Attempts a TEAM login. A team can only log in to a game that
     * has been Published (Doc 3, Slide 7 - game must be published first).
     */
    public static function loginTeam(string $username, string $password): bool
    {
        $db = Database::getInstance();
        $team = $db->fetchOne(
            'SELECT t.team_id, t.team_name, t.password_hash, t.is_active,
                    t.game_id, g.status AS game_status
             FROM team_master t
             INNER JOIN game_master g ON g.game_id = t.game_id
             WHERE t.username = :username LIMIT 1',
            [':username' => $username]
        );

        if (!$team || (int)$team['is_active'] !== 1) {
            Logger::warning("Failed team login attempt for username: $username");
            return false;
        }

        if ($team['game_status'] !== GAME_STATUS_PUBLISHED) {
            Logger::warning("Team '{$username}' tried to log in to an unpublished game.");
            return false;
        }

        if (!password_verify($password, $team['password_hash'])) {
            Logger::warning("Wrong password for team username: $username");
            return false;
        }

        session_regenerate_id(true);
        Session::set(SESSION_TEAM_ID, (int)$team['team_id']);
        Session::set(SESSION_TEAM_NAME, $team['team_name']);
        Session::set(SESSION_GAME_ID, (int)$team['game_id']);
        Logger::activity("Team '{$team['team_name']}' logged in.");
        return true;
    }

    public static function logout(): void
    {
        $who = Session::isAdminLoggedIn()
            ? 'Admin #' . Session::currentAdminId()
            : 'Team #' . Session::currentTeamId();
        Logger::activity("$who logged out.");
        Session::destroyAll();
    }

    /** Call at the top of every admin/*.php page (except auth/login.php). */
    public static function requireAdmin(): void
    {
        if (!Session::isAdminLoggedIn()) {
            header('Location: ' . ADMIN_URL . '/auth/login.php');
            exit;
        }
    }

    /** Call at the top of every player/*.php page (except auth/login.php). */
    public static function requireTeam(): void
    {
        if (!Session::isTeamLoggedIn()) {
            header('Location: ' . PLAYER_URL . '/auth/login.php');
            exit;
        }
    }

    /** Hashes a plain-text password for storing in admin_user / team_master. */
    public static function hashPassword(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT);
    }
}
