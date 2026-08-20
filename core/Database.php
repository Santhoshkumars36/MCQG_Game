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
    require_once __DIR__ . '/../config/constants.php';
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

    /** Alias for fetchValue() */
    public function fetchColumn(string $sql, array $params = [])
    {
        return $this->fetchValue($sql, $params);
    }

    /**
     * ORM-style INSERT: pass table name + associative data array.
     * e.g. $db->insert('game_master', ['game_name' => 'Test', 'status' => 'Draft'])
     * Returns the new auto-increment ID.
     */
    public function insert(string $table, array $data): string
    {
        // Detect if first arg looks like raw SQL (contains spaces)
        if (strpos($table, ' ') !== false) {
            // Legacy raw-SQL call: insert($sql, $params)
            $stmt = $this->connection->prepare($table);
            $stmt->execute($data);
            return $this->connection->lastInsertId();
        }

        $cols   = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $cols) . '`) '
             . 'VALUES (' . implode(', ', $placeholders) . ')';
        $params = [];
        foreach ($data as $col => $val) {
            $params[':' . $col] = $val;
        }
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $this->connection->lastInsertId();
    }

    /**
     * ORM-style UPDATE: pass table, data array, WHERE clause, WHERE params.
     * e.g. $db->update('game_master', ['game_name' => 'New'], 'game_id = :g', ['g' => 1])
     * Returns number of affected rows.
     */
    public function update(string $table, array $data, string $where = '', array $whereParams = []): int
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[] = '`' . $col . '` = :set_' . $col;
            $params[':set_' . $col] = $val;
        }
        $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $sets);
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
            foreach ($whereParams as $k => $v) {
                // Support both ':key' and 'key' format
                $key = (str_starts_with($k, ':')) ? $k : ':' . $k;
                $params[$key] = $v;
            }
        }
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Run an UPDATE or DELETE raw SQL and return the number of affected rows. */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Run a SQL query (SELECT, INSERT, UPDATE, DELETE).
     * Automatically dispatches to fetchAll for SELECTs or execute for write operations.
     */
    public function query(string $sql, array $params = [])
    {
        $trimmed = ltrim($sql);
        if (strncasecmp($trimmed, 'SELECT', 6) === 0 || strncasecmp($trimmed, 'SHOW', 4) === 0) {
            return $this->fetchAll($sql, $params);
        }
        return $this->execute($sql, $params);
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

