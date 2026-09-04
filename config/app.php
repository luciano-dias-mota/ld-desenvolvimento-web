<?php

return [
    'name' => $_ENV['APP_NAME'] ?? 'PHP Quest',
    'env' => $_ENV['APP_ENV'] ?? 'local',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
    'timezone' => 'America/Sao_Paulo',
    'locale' => 'pt_BR',
];