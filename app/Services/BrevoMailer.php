<?php

namespace App\Services;

final class BrevoMailer
{
    public function sendEmailVerification(string $email, string $name, string $verificationUrl): bool
    {
        $apiKey = trim((string) ($_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY') ?: ''));
        $fromEmail = trim((string) ($_ENV['MAIL_FROM_EMAIL'] ?? getenv('MAIL_FROM_EMAIL') ?: ''));
        $fromName = trim((string) ($_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'LD Desenvolvimento Web'));

        if ($apiKey === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (!function_exists('curl_init')) {
            error_log('Extensão cURL não disponível; e-mail de verificação não enviado.');
            return false;
        }

        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeUrl = htmlspecialchars($verificationUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $payload = [
            'sender' => ['name' => $fromName, 'email' => $fromEmail],
            'to' => [['email' => $email, 'name' => $name]],
            'subject' => 'Confirme seu e-mail — LD Desenvolvimento Web',
            'htmlContent' => '<html><body style="font-family:Arial,sans-serif">'
                . '<h2>Confirme seu e-mail</h2>'
                . '<p>Olá, ' . $safeName . '.</p>'
                . '<p>Confirme seu endereço para concluir a verificação da sua conta na LD Desenvolvimento Web.</p>'
                . '<p><a href="' . $safeUrl . '" style="display:inline-block;padding:12px 18px;background:#f97316;color:#fff;text-decoration:none;border-radius:8px">Confirmar e-mail</a></p>'
                . '<p>Se o botão não funcionar, copie este endereço:</p><p>' . $safeUrl . '</p>'
                . '<p>Se você não criou esta conta, ignore esta mensagem.</p>'
                . '</body></html>',
            'textContent' => "Confirme seu e-mail na LD Desenvolvimento Web: {$verificationUrl}",
        ];

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'content-type: application/json',
                'api-key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status < 200 || $status >= 300) {
            error_log('Falha ao enviar verificação pela Brevo. HTTP ' . $status . ($error !== '' ? ' - ' . $error : ''));
            return false;
        }

        return true;
    }
}
