<?php

namespace App\Core;

use PDO;

abstract class Controller
{
    private ?PDO $connection = null;

    protected function db(): PDO
    {
        if ($this->connection === null) {
            $this->connection = Database::getInstance()->getConnection();
        }

        return $this->connection;
    }

    protected function view(string $view, array $data = []): void
    {
        $viewPath = __DIR__ . '/../Views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewPath)) {
            throw new \RuntimeException("View {$view} não encontrada.");
        }

        if (str_starts_with($view, 'layouts/') || str_starts_with($view, 'errors/') || $view === 'certificado/show') {
            $this->includeViewFile($viewPath, $data);
            return;
        }

        $layout = (str_starts_with($view, 'auth/') || $view === 'certificado/validar')
            ? 'auth'
            : 'main';

        $layoutPath = __DIR__ . '/../Views/layouts/' . $layout . '.php';
        if (!is_file($layoutPath)) {
            $this->includeViewFile($viewPath, $data);
            return;
        }

        if (array_key_exists('contentView', $data)) {
            throw new \InvalidArgumentException('A variável contentView é reservada pelo renderer.');
        }

        $data['contentView'] = $viewPath;
        $this->includeViewFile($layoutPath, $data);
    }

    private function includeViewFile(string $__viewFileInternal, array $__viewDataInternal): void
    {
        $reserved = ['__viewFileInternal', '__viewDataInternal'];
        if (array_intersect($reserved, array_keys($__viewDataInternal)) !== []) {
            throw new \InvalidArgumentException('A view recebeu uma variável com nome reservado.');
        }

        (function () use ($__viewFileInternal, $__viewDataInternal): void {
            extract($__viewDataInternal, EXTR_SKIP);
            require $__viewFileInternal;
        })->call($this);
    }

    protected function redirect(string $url, int $status = 302): void
    {
        if (headers_sent()) {
            throw new \RuntimeException('Não foi possível redirecionar: os cabeçalhos HTTP já foram enviados.');
        }

        $location = str_starts_with($url, '/') ? Url::to($url) : $url;
        header('Location: ' . $location, true, $status);
        exit;
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function csrfField(): string
    {
        $token = Session::get('csrf_token');

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set('csrf_token', $token);
        }

        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
            . '">';
    }

    protected function validateCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        $sessionToken = Session::get('csrf_token');

        if (!is_string($token) || !is_string($sessionToken) || $token === '' || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    protected function notFound(): void
    {
        http_response_code(404);
        $this->view('errors/404');
        exit;
    }
}
