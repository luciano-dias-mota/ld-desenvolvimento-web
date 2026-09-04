<?php

namespace App\Core;

class Router
{
    private array $routes = [];
    private array $middlewares = [];
    private string $currentRoute = '';

    public function get(string $path, string $controllerAction): void
    {
        $this->addRoute('GET', $path, $controllerAction);
    }

    public function post(string $path, string $controllerAction): void
    {
        $this->addRoute('POST', $path, $controllerAction);
    }

    private function addRoute(string $method, string $path, string $controllerAction): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'controllerAction' => $controllerAction,
            'middlewares' => []
        ];
        // Armazena o último índice para adicionar middlewares
        $this->currentRoute = array_key_last($this->routes);
    }

    public function middleware(string $middleware): self
    {
        $this->routes[$this->currentRoute]['middlewares'][] = $middleware;
        return $this;
    }

    public function dispatch(string $method, string $uri): void
    {
        // Remove query string
        $uri = strtok($uri, '?');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            // Converte parâmetros {param} para regex
            $pattern = preg_replace('#\{[a-zA-Z_]+\}#', '([a-zA-Z0-9\-_]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                // Remove o primeiro elemento (match completo)
                array_shift($matches);

                // Executa middlewares
                foreach ($route['middlewares'] as $mw) {
                    $this->handleMiddleware($mw);
                }

                // Chama o controller
                [$controllerName, $action] = explode('@', $route['controllerAction']);
                $controllerClass = 'App\\Controllers\\' . $controllerName;

                if (!class_exists($controllerClass)) {
                    http_response_code(500);
                    die("Controller $controllerClass não encontrado.");
                }

                $controller = new $controllerClass();

                if (!method_exists($controller, $action)) {
                    http_response_code(500);
                    die("Método $action não encontrado no controller $controllerClass.");
                }

                // Passa os parâmetros da URL para o método
                call_user_func_array([$controller, $action], $matches);
                return;
            }
        }

        // 404
        http_response_code(404);
        $controller = new \App\Controllers\ErrorController();
        $controller->notFound();
    }

    private function handleMiddleware(string $middleware): void
    {
        switch ($middleware) {
            case 'auth':
                if (!Auth::check()) {
                    header('Location: /login');
                    exit;
                }
                break;
            case 'admin':
                if (!Auth::check() || !Auth::isAdmin()) {
                    http_response_code(403);
                    die('Acesso negado.');
                }
                break;
            case 'guest':
                if (Auth::check()) {
                    header('Location: /dashboard');
                    exit;
                }
                break;
            default:
                // Pode carregar middleware customizado
                break;
        }
    }
}