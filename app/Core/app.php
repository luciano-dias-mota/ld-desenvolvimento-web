<?php

namespace App\Core;

class App
{
    public static function run(): void
    {
        try {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
            $dotenv->safeLoad();

            self::configureErrorDisplay();
            Session::start();

            $router = new Router();
            require __DIR__ . '/../../routes/web.php';
            require __DIR__ . '/../../routes/admin.php';

            $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            $router->dispatch($method, $uri);
        } catch (\Throwable $e) {
            self::handleException($e);
        }
    }

    private static function configureErrorDisplay(): void
    {
        $debug = self::isDebug();
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('display_startup_errors', $debug ? '1' : '0');
        error_reporting(E_ALL);
    }

    private static function isDebug(): bool
    {
        $value = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: 'false';
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function handleException(\Throwable $e): void
    {
        error_log(sprintf(
            "[%s] %s in %s:%d\n%s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        if (self::isDebug()) {
            echo '<h1>Erro interno</h1><pre>'
                . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                . '</pre>';
            exit;
        }

        echo '<h1>Erro interno</h1><p>Não foi possível concluir a solicitação. Tente novamente mais tarde.</p>';
        exit;
    }
}
