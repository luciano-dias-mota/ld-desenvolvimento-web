<?php

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = []): void
    {
        // Extrai dados para variáveis
        extract($data);

        // Define caminho base das views
        $viewPath = __DIR__ . '/../Views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            throw new \Exception("View {$view} não encontrada.");
        }

        // Se for uma view que usa layout, carrega o layout
        // Vamos usar uma convenção: se a view começar com 'layouts/' não usa layout
        if (strpos($view, 'layouts/') === 0) {
            require $viewPath;
            return;
        }

        // Verifica se a view é para layout principal
        // Usaremos um layout padrão para views autenticadas, exceto auth e admin
        $layout = 'main';
        if (strpos($view, 'auth/') === 0 || strpos($view, 'errors/') === 0) {
            $layout = 'auth';
        } elseif (strpos($view, 'admin/') === 0) {
            // Pode usar layout admin ou main
            $layout = 'main';
        }

        // Carrega o layout e dentro dele a view
        $contentView = $viewPath;
        require __DIR__ . '/../Views/layouts/' . $layout . '.php';
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