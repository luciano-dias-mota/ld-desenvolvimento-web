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
            self::registerFatalHandler();
            self::sendSecurityHeaders();
            Session::start();

            $router = new Router();
            $router
                ->registerMiddleware('auth', \App\Middleware\AuthMiddleware::class)
                ->registerMiddleware('admin', \App\Middleware\AdminMiddleware::class)
                ->registerMiddleware('guest', \App\Middleware\GuestMiddleware::class)
                ->registerMiddleware('learning', \App\Middleware\LearningMiddleware::class);

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

    private static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: https://lh3.googleusercontent.com; connect-src 'self' https://accounts.google.com/gsi/; script-src 'self' 'unsafe-inline' https://accounts.google.com/gsi/client; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://accounts.google.com/gsi/style; font-src 'self' https://fonts.gstatic.com data:; frame-src https://accounts.google.com/gsi/ https://www.youtube-nocookie.com https://player.vimeo.com");

        $isProduction = strtolower((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production')) === 'production';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        if ($isProduction && $https) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    private static function registerFatalHandler(): void
    {
        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if (!is_array($error) || !in_array($error['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            error_log(sprintf(
                '[FatalError] %s in %s:%d',
                (string) ($error['message'] ?? 'Erro fatal'),
                (string) ($error['file'] ?? 'desconhecido'),
                (int) ($error['line'] ?? 0)
            ));

            if (headers_sent()) {
                return;
            }

            http_response_code(500);
            if (self::wantsJson()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Erro interno.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>Erro interno</h1><p>Não foi possível concluir a solicitação.</p>';
        });
    }

    private static function isDebug(): bool
    {
        $value = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: 'false';
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
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
        }

        if (self::wantsJson()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            $payload = ['error' => 'Erro interno.'];
            if (self::isDebug()) {
                $payload['debug'] = $e->getMessage();
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if (!headers_sent()) {
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
