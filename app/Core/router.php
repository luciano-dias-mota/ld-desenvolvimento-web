<?php

namespace App\Core;

class Router
{
    private array $routes = [];
    private int $currentRoute = 0;

    public function get(string $path, string $controllerAction): self
    {
        return $this->addRoute('GET', $path, $controllerAction);
    }

    public function post(string $path, string $controllerAction): self
    {
        return $this->addRoute('POST', $path, $controllerAction);
    }

    private function addRoute(string $method, string $path, string $controllerAction): self
    {
        if (!str_starts_with($path, '/')) {
            throw new \InvalidArgumentException('Toda rota deve começar com /.');
        }

        if (!str_contains($controllerAction, '@')) {
            throw new \InvalidArgumentException('A ação da rota deve usar o formato Controller@metodo.');
        }

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'controllerAction' => $controllerAction,
            'middlewares' => [],
        ];

        $this->currentRoute = array_key_last($this->routes);
        return $this;
    }

    public function middleware(string $middleware): self
    {
        if (!isset($this->routes[$this->currentRoute])) {
            throw new \LogicException('Nenhuma rota disponível para receber middleware.');
        }

        $this->routes[$this->currentRoute]['middlewares'][] = $middleware;
        return $this;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rawurldecode($path);

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($basePath !== '' && $basePath !== '.' && $basePath !== '/' && str_starts_with($path, $basePath . '/')) {
            $path = substr($path, strlen($basePath));
        }

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            $pattern = preg_replace(
                '#\{[a-zA-Z_][a-zA-Z0-9_]*\}#',
                '([a-zA-Z0-9\-_]+)',
                $route['path']
            );
            $pattern = '#^' . $pattern . '$#';

            if (!preg_match($pattern, $path, $matches)) {
                continue;
            }

            array_shift($matches);

            foreach ($route['middlewares'] as $middleware) {
                $this->handleMiddleware($middleware);
            }

            [$controllerName, $action] = explode('@', $route['controllerAction'], 2);
            $controllerClass = 'App\\Controllers\\' . $controllerName;

            if (!class_exists($controllerClass)) {
                throw new \RuntimeException('Controller configurado na rota não foi encontrado.');
            }

            $controller = new $controllerClass();
            if (!is_callable([$controller, $action])) {
                throw new \RuntimeException('Ação configurada na rota não está disponível.');
            }

            call_user_func_array([$controller, $action], $matches);
            return;
        }

        http_response_code(404);
        $controller = new \App\Controllers\ErrorController();
        $controller->notFound();
    }

    private function handleMiddleware(string $middleware): void
    {
        switch ($middleware) {
            case 'auth':
                if (!Auth::check()) {
                    $this->redirect('/login');
                }
                return;

            case 'admin':
                if (!Auth::check() || !Auth::isAdmin()) {
                    http_response_code(403);
                    echo 'Acesso negado.';
                    exit;
                }
                return;

            case 'guest':
                if (Auth::check()) {
                    $this->redirect('/dashboard');
                }
                return;

            default:
                throw new \RuntimeException('Middleware não reconhecido: ' . $middleware);
        }
    }

    private function redirect(string $path): void
    {
        if (function_exists('url')) {
            $location = url($path);
        } else {
            $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
            $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
            $location = (($basePath !== '' && $basePath !== '.' && $basePath !== '/') ? $basePath : '') . $path;
        }

        header('Location: ' . $location, true, 302);
        exit;
    }
}
