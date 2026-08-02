<?php
/**
 * =====================================================================
 * MCQG - core/Database.php
 * Purpose: Single PDO connection (Singleton pattern) shared across the
 *          whole request, plus small helper methods so every other
 *          file (classes/, engine/, ajax/) never has to write raw
 *          PDO boilerplate or worry about SQL injection.
 * =====================================================================
 */

if (!defined('MCQG_APP')) {
    die('Direct access not permitted.');
}

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    private function __construct()
    {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
        ];
        $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Run a SELECT and return every matching row.
     * @param string $sql    e.g. "SELECT * FROM game_master WHERE status = :status"
     * @param array  $params e.g. [':status' => 'Published']
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Run a SELECT and return only the first matching row (or null). */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Run a SELECT and return a single scalar value (e.g. COUNT(*), SUM(...)). */
    public function fetchValue(string $sql, array $params = [])
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false ? null : $value;
    }

    /** Run an INSERT and return the new auto-increment ID. */
    public function insert(string $sql, array $params = []): string
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $this->connection->lastInsertId();
    }

    /** Run an UPDATE or DELETE and return the number of affected rows. */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function beginTransaction(): bool { return $this->connection->beginTransaction(); }
    public function commit(): bool           { return $this->connection->commit(); }
    public function rollBack(): bool         { return $this->connection->rollBack(); }

    // Block cloning/unserializing so the Singleton pattern cannot be bypassed
    private function __clone() {}
    public function __wakeup()
    {
        throw new Exception('Cannot unserialize a Database singleton.');
    }
}
