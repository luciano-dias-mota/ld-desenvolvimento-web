<?php

namespace App\Core;

abstract class Controller
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    protected function view(string $view, array $data = []): void
    {
        // Extrai dados para variáveis
        extract($data);

        // Define caminho base das views
        $viewPath = __DIR__ . '/../Views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            throw new \Exception("View {$view} não encontrada.");
        }

        // Se a view já é um layout completo (ex: auth/login), carrega diretamente
        if (strpos($view, 'layouts/') === 0) {
            require $viewPath;
            return;
        }

        // Verifica se a view usa um layout padrão
        $layout = null;
        if (strpos($view, 'auth/') === 0 || strpos($view, 'errors/') === 0) {
            $layout = 'auth';
        } elseif (strpos($view, 'admin/') === 0) {
            $layout = 'main';
        } elseif (strpos($view, 'certificado/') === 0) {
            // Certificado tem layout próprio (imprime página sem header)
            $layout = 'certificado';
        } else {
            $layout = 'main';
        }

        // Carrega o layout e dentro dele a view
        $contentView = $viewPath;
        $layoutPath = __DIR__ . '/../Views/layouts/' . $layout . '.php';

        if (file_exists($layoutPath)) {
            require $layoutPath;
        } else {
            // Fallback: carrega a view sem layout
            require $viewPath;
        }
    }

    protected function layout(string $layoutName): void
    {
        // Método para ser chamado dentro de uma view, mas na prática é tratado no view()
        // Mantido por compatibilidade, mas não é usado diretamente.
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function json(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function csrfField(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set('csrf_token', $token);
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    protected function validateCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if ($token !== Session::get('csrf_token')) {
            http_response_code(403);
            die('CSRF token inválido.');
        }
    }
}