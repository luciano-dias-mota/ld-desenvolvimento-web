<?php

namespace App\Core;

use PDO;

abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected static array $fillable = [];

    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    protected static function getDB(): PDO
    {
        return Database::getInstance()->getConnection();
    }

    public static function getTable(): string
    {
        static::assertIdentifier(static::$table);
        return static::$table;
    }

    public static function all(): array
    {
        $table = static::quoteIdentifier(static::$table);
        $stmt = static::getDB()->query("SELECT * FROM {$table}");
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $table = static::quoteIdentifier(static::$table);
        $primaryKey = static::quoteIdentifier(static::$primaryKey);
        $stmt = static::getDB()->prepare("SELECT * FROM {$table} WHERE {$primaryKey} = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function where(string $column, mixed $value): array
    {
        $table = static::quoteIdentifier(static::$table);
        $columnSql = static::quoteIdentifier($column);
        $stmt = static::getDB()->prepare("SELECT * FROM {$table} WHERE {$columnSql} = ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll();
    }

    public static function whereAll(array $conditions): array
    {
        if ($conditions === []) {
            throw new \InvalidArgumentException('whereAll() exige ao menos uma condição.');
        }

        $table = static::quoteIdentifier(static::$table);
        $whereClauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $whereClauses[] = static::quoteIdentifier((string) $column) . ' = ?';
            $params[] = $value;
        }

        $stmt = static::getDB()->prepare(
            "SELECT * FROM {$table} WHERE " . implode(' AND ', $whereClauses)
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function firstWhere(string $column, mixed $value): ?array
    {
        $table = static::quoteIdentifier(static::$table);
        $columnSql = static::quoteIdentifier($column);
        $stmt = static::getDB()->prepare("SELECT * FROM {$table} WHERE {$columnSql} = ? LIMIT 1");
        $stmt->execute([$value]);
        return $stmt->fetch() ?: null;
    }

    public static function firstWhereAll(array $conditions): ?array
    {
        if ($conditions === []) {
            throw new \InvalidArgumentException('firstWhereAll() exige ao menos uma condição.');
        }

        $table = static::quoteIdentifier(static::$table);
        $whereClauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $whereClauses[] = static::quoteIdentifier((string) $column) . ' = ?';
            $params[] = $value;
        }

        $stmt = static::getDB()->prepare(
            "SELECT * FROM {$table} WHERE " . implode(' AND ', $whereClauses) . ' LIMIT 1'
        );
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $data = static::sanitizeWriteData($data);
        if ($data === []) {
            throw new \InvalidArgumentException('Nenhum dado válido informado para create().');
        }

        $table = static::quoteIdentifier(static::$table);
        $columns = array_keys($data);
        $quotedColumns = array_map([static::class, 'quoteIdentifier'], $columns);
        $placeholders = ':' . implode(', :', $columns);

        $stmt = static::getDB()->prepare(
            "INSERT INTO {$table} (" . implode(', ', $quotedColumns) . ") VALUES ({$placeholders})"
        );
        $stmt->execute($data);

        return (int) static::getDB()->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $data = static::sanitizeWriteData($data);
        if ($data === []) {
            return false;
        }

        $table = static::quoteIdentifier(static::$table);
        $primaryKey = static::quoteIdentifier(static::$primaryKey);
        $set = [];

        foreach (array_keys($data) as $key) {
            $set[] = static::quoteIdentifier($key) . " = :{$key}";
        }

        $data['__pk'] = $id;
        $stmt = static::getDB()->prepare(
            "UPDATE {$table} SET " . implode(', ', $set) . " WHERE {$primaryKey} = :__pk"
        );

        return $stmt->execute($data);
    }

    public static function delete(int $id): bool
    {
        $table = static::quoteIdentifier(static::$table);
        $primaryKey = static::quoteIdentifier(static::$primaryKey);
        $stmt = static::getDB()->prepare("DELETE FROM {$table} WHERE {$primaryKey} = ?");
        return $stmt->execute([$id]);
    }

    public function save(): bool
    {
        $data = get_object_vars($this);
        unset($data['db']);

        if (!isset($data['id'])) {
            return false;
        }

        $id = (int) $data['id'];
        unset($data['id']);
        return static::update($id, $data);
    }

    protected static function sanitizeWriteData(array $data): array
    {
        foreach (array_keys($data) as $column) {
            static::assertIdentifier((string) $column);
        }

        if (static::$fillable !== []) {
            $unknown = array_diff(array_keys($data), static::$fillable);
            if ($unknown !== []) {
                throw new \InvalidArgumentException(
                    'Campos não permitidos: ' . implode(', ', $unknown)
                );
            }
        }

        return $data;
    }

    protected static function assertIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("Identificador SQL inválido: {$identifier}");
        }
    }

    protected static function quoteIdentifier(string $identifier): string
    {
        static::assertIdentifier($identifier);
        return '`' . $identifier . '`';
    }
}
