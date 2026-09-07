<?php

namespace App\Services;

final class GoogleIdentityService
{
    public function isEnabled(): bool
    {
        return $this->clientId() !== '';
    }

    public function clientId(): string
    {
        return trim((string) ($_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?: ''));
    }

    public function verify(string $credential): array
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Login com Google não está configurado.');
        }

        if (!class_exists(\Google\Client::class)) {
            throw new \RuntimeException('Biblioteca google/apiclient não instalada. Execute composer install.');
        }

        $client = new \Google\Client(['client_id' => $this->clientId()]);
        $payload = $client->verifyIdToken($credential);
        if (!is_array($payload)) {
            throw new \RuntimeException('Credencial Google inválida.');
        }

        $sub = trim((string) ($payload['sub'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $name = trim((string) ($payload['name'] ?? ''));

        if ($sub === '' || strlen($sub) > 255) {
            throw new \RuntimeException('Conta Google sem identificador válido.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            throw new \RuntimeException('Conta Google sem e-mail válido.');
        }

        if ($name === '') {
            $name = strstr($email, '@', true) ?: 'Aluno';
        }
        $name = function_exists('mb_substr') ? mb_substr($name, 0, 100) : substr($name, 0, 100);

        $emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hostedDomain = trim((string) ($payload['hd'] ?? ''));
        $authoritativeEmail = $emailVerified
            && (str_ends_with($email, '@gmail.com') || $hostedDomain !== '');

        return [
            'sub' => $sub,
            'email' => $email,
            'name' => $name,
            'email_verified' => $emailVerified,
            'authoritative_email' => $authoritativeEmail,
        ];
    }
}
