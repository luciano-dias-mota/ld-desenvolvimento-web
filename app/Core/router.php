<?php

namespace App\Core;

class Router
{
    private array $routes = [];
    private int $currentRoute = 0;
    private array $middlewareMap = [];

    public function get(string $path, string $controllerAction): self
    {
        return $this->addRoute('GET', $path, $controllerAction);
    }

    public function post(string $path, string $controllerAction): self
    {
        return $this->addRoute('POST', $path, $controllerAction);
    }

    public function put(string $path, string $controllerAction): self
    {
        return $this->addRoute('PUT', $path, $controllerAction);
    }

    public function patch(string $path, string $controllerAction): self
    {
        return $this->addRoute('PATCH', $path, $controllerAction);
    }

    public function delete(string $path, string $controllerAction): self
    {
        return $this->addRoute('DELETE', $path, $controllerAction);
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
            'pattern' => $this->compilePattern($path),
            'controllerAction' => $controllerAction,
            'middlewares' => [],
        ];

        $this->currentRoute = array_key_last($this->routes);
        return $this;
    }

    public function registerMiddleware(string $name, string $class): self
    {
        if ($name === '' || !class_exists($class)) {
            throw new \InvalidArgumentException('Middleware inválido: ' . $name);
        }

        $this->middlewareMap[$name] = $class;
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
        $method = strtoupper($method);

        $basePath = Url::basePath();
        if ($basePath !== '') {
            if ($path === $basePath) {
                $path = '/';
            } elseif (str_starts_with($path, $basePath . '/')) {
                $path = substr($path, strlen($basePath));
            }
        }

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowedMethods[] = $route['method'];
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

        if ($allowedMethods !== []) {
            $allowedMethods = array_values(array_unique($allowedMethods));
            http_response_code(405);
            header('Allow: ' . implode(', ', $allowedMethods));
            echo 'Método não permitido.';
            return;
        }

        http_response_code(404);
        $controller = new \App\Controllers\ErrorController();
        $controller->notFound();
    }

    private function compilePattern(string $path): string
    {
        $parts = preg_split(
            '/(\{[A-Za-z_][A-Za-z0-9_]*\})/',
            $path,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        $pattern = '';
        foreach ($parts ?: [] as $part) {
            if (preg_match('/^\{[A-Za-z_][A-Za-z0-9_]*\}$/', $part)) {
                $pattern .= '([A-Za-z0-9\-_]+)';
            } else {
                $pattern .= preg_quote($part, '#');
            }
        }

        return '#^' . $pattern . '$#';
    }

    private function handleMiddleware(string $middleware): void
    {
        $class = $this->middlewareMap[$middleware] ?? null;
        if (!is_string($class) || !class_exists($class)) {
            throw new \RuntimeException('Middleware não reconhecido: ' . $middleware);
        }

        $instance = new $class();
        if (!is_callable([$instance, 'handle'])) {
            throw new \RuntimeException('Middleware sem método handle(): ' . $middleware);
        }

        $instance->handle();
    }

}