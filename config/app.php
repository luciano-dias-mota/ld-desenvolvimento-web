<?php

return [
    'name' => $_ENV['APP_NAME'] ?? getenv('APP_NAME') ?: 'LD Desenvolvimento Web',
    'env' => $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production',
    'url' => $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost/ld-desenvolvimento-web/public',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
    'session_lifetime' => max(1, (int) ($_ENV['SESSION_LIFETIME'] ?? getenv('SESSION_LIFETIME') ?: 120)),
];
