<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    private function __construct()
    {
        $config = require __DIR__ . '/../../config/database.php';
        $db = self::validateConfig($config);

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['database'],
                $db['charset']
            );

            $this->connection = new PDO($dsn, $db['username'], $db['password'], $db['options']);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            error_log('Falha na conexão com o banco de dados: ' . $e->getMessage());
            throw new \RuntimeException('Não foi possível conectar ao banco de dados.', 0, $e);
        }
    }

    private static function validateConfig(mixed $config): array
    {
        if (!is_array($config)) {
            throw new \RuntimeException('Configuração de banco inválida.');
        }

        $default = $config['default'] ?? null;
        $connections = $config['connections'] ?? null;
        if (!is_string($default) || $default === '' || !is_array($connections) || !isset($connections[$default]) || !is_array($connections[$default])) {
            throw new \RuntimeException('Conexão padrão do banco não está configurada corretamente.');
        }

        $db = $connections[$default];
        $required = ['host', 'port', 'database', 'username', 'password', 'charset'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $db)) {
                throw new \RuntimeException('Configuração de banco incompleta: campo obrigatório ausente.');
            }
        }

        $db['host'] = trim((string) $db['host']);
        $db['database'] = trim((string) $db['database']);
        $db['username'] = trim((string) $db['username']);
        $db['password'] = (string) $db['password'];
        $db['charset'] = trim((string) $db['charset']);
        $db['port'] = (int) $db['port'];
        $db['options'] = isset($db['options']) && is_array($db['options']) ? $db['options'] : [];

        if ($db['host'] === '' || $db['database'] === '' || $db['username'] === '') {
            throw new \RuntimeException('Host, banco e usuário do banco são obrigatórios.');
        }

        if ($db['port'] < 1 || $db['port'] > 65535) {
            throw new \RuntimeException('Porta do banco inválida.');
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $db['charset'])) {
            throw new \RuntimeException('Charset do banco inválido.');
        }

        return $db;
    }

    public static function getInstance(): self
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
}
