<?php

namespace App\Core;

use PDO;

abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';

    protected $db;

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
        return static::$table;
    }

    public static function all(): array
    {
        $db = self::getDB();
        $stmt = $db->query("SELECT * FROM " . static::$table);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $db = self::getDB();
        $stmt = $db->prepare("SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function where(string $column, $value): array
    {
        $db = self::getDB();
        $stmt = $db->prepare("SELECT * FROM " . static::$table . " WHERE $column = ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll();
    }

    public static function whereAll(array $conditions): array
    {
        $db = self::getDB();
        $whereClauses = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            $whereClauses[] = "$column = ?";
            $params[] = $value;
        }
        $whereSql = implode(' AND ', $whereClauses);
        $stmt = $db->prepare("SELECT * FROM " . static::$table . " WHERE $whereSql");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function firstWhere(string $column, $value): ?array
    {
        $db = self::getDB();
        $stmt = $db->prepare("SELECT * FROM " . static::$table . " WHERE $column = ? LIMIT 1");
        $stmt->execute([$value]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $db = self::getDB();
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $stmt = $db->prepare("INSERT INTO " . static::$table . " ($columns) VALUES ($placeholders)");
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $db = self::getDB();
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "$key = :$key";
        }
        $set = implode(', ', $set);
        $data['id'] = $id;
        $stmt = $db->prepare("UPDATE " . static::$table . " SET $set WHERE " . static::$primaryKey . " = :id");
        return $stmt->execute($data);
    }

    public static function delete(int $id): bool
    {
        $db = self::getDB();
        $stmt = $db->prepare("DELETE FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?");
        return $stmt->execute([$id]);
    }

    // Retorna o primeiro registro de uma consulta com condições múltiplas
    public static function firstWhereAll(array $conditions): ?array
    {
        $db = self::getDB();
        $whereClauses = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            $whereClauses[] = "$column = ?";
            $params[] = $value;
        }
        $whereSql = implode(' AND ', $whereClauses);
        $stmt = $db->prepare("SELECT * FROM " . static::$table . " WHERE $whereSql LIMIT 1");
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    // Método para obter o último ID inserido (para instância)
    public function save(): bool
    {
        $data = get_object_vars($this);
        unset($data['db']); // remove propriedade $db
        if (isset($data['id'])) {
            return self::update($data['id'], $data);
        }
        return false;
    }
}