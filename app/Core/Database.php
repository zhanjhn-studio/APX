<?php
declare(strict_types=1);
namespace App\Core;

/**
 * 轻量 PDO 封装。所有查询一律预处理，绝不拼接 SQL。
 * 统一使用 utf8mb4，游标分页经由调用方在 SQL 中处理。
 */
class Database
{
    private static ?Database $instance = null;
    private \PDO $pdo;

    public function __construct()
    {
        $cfg = Config::get('database');
        if (empty($cfg['host']) || empty($cfg['dbname'])) {
            throw new \RuntimeException('数据库未配置，请先运行 install.php');
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'] ?? 3306,
            $cfg['dbname'],
            $cfg['charset'] ?? 'utf8mb4'
        );
        $this->pdo = new \PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options'] ?? []);
        $this->pdo->exec("SET NAMES '" . ($cfg['charset'] ?? 'utf8mb4') . "' COLLATE '" . ($cfg['collation'] ?? 'utf8mb4_unicode_ci') . "'");
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function column(string $sql, array $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function insertIgnore(string $table, array $data): int
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = 'INSERT IGNORE INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = array_map(fn($c) => '`' . $c . '` = :u_' . $c, array_keys($data));
        $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        $params = [];
        foreach ($data as $k => $v) {
            $params[':u_' . $k] = $v;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params, $whereParams));
        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM `' . $table . '` WHERE ' . $where);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function statement(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function inParams(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }
}
