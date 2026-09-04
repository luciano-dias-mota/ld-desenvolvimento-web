<?php

namespace App\Core;

class App
{
    public static function run(): void
    {
        // Inicia sessão
        Session::start();

        // Carrega .env
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        // Carrega rotas
        $router = new Router();
        require __DIR__ . '/../../routes/web.php';
        require __DIR__ . '/../../routes/admin.php';

        // Dispatch
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        $router->dispatch($method, $uri);
    }
}