<?php
$headerUser = \App\Core\Auth::user();
$headerIsGuest = \App\Core\Auth::isGuest();

$headerName = trim((string) ($headerUser['name'] ?? 'Aluno'));
$headerEmail = trim((string) ($headerUser['email'] ?? ''));
$headerFirstName = $headerName !== '' ? (preg_split('/\s+/', $headerName)[0] ?? $headerName) : 'Aluno';
$headerInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($headerName !== '' ? $headerName : 'A', 0, 1))
    : strtoupper(substr($headerName !== '' ? $headerName : 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="theme-color" content="#07090c">
    <title><?= e($title ?? ($_ENV['APP_NAME'] ?? 'LD Desenvolvimento Web')) ?></title>
    <script>(function(){try{const saved=localStorage.getItem('phpquest-theme');document.documentElement.setAttribute('data-theme',saved==='light'?'light':'dark');}catch(e){}})();</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<div class="app">
<header class="header"><div class="container">
    <a href="<?= url('/dashboard') ?>" class="logo" aria-label="LD Desenvolvimento Web - Mapa da Jornada"><span class="logo-mark">&lt;/&gt;</span><span class="logo-word"><strong>LD</strong> Desenvolvimento Web</span></a>
    <nav aria-label="Navegação principal">
        <a href="<?= url('/dashboard') ?>">Mapa</a>
        <?php if (\App\Core\Auth::isAdmin()): ?><a href="<?= url('/admin/dashboard') ?>">Admin</a><?php endif; ?>
        <button type="button" class="theme-toggle" data-theme-toggle aria-label="Alternar tema"><span class="theme-toggle-icon" data-theme-icon>☀</span><span class="theme-toggle-label" data-theme-label>Claro</span></button>

        <?php if ($headerIsGuest): ?>
            <a href="<?= url('/register') ?>" class="btn btn-primary btn-small">Criar conta</a>
            <form action="<?= url('/visitante/sair') ?>" method="POST" class="header-form"><?= csrf_field() ?><button type="submit" class="btn btn-outline btn-small">Sair do visitante</button></form>
        <?php elseif ($headerUser): ?>
            <details class="user-account-menu">
                <summary class="user-account-trigger" aria-label="Abrir painel do usuário">
                    <span class="user-avatar" aria-hidden="true"><?= e($headerInitial) ?></span>
                    <span class="user-trigger-copy">
                        <strong><?= e($headerFirstName) ?></strong>
                        <small><?= (int) ($headerUser['xp'] ?? 0) ?> XP</small>
                    </span>
                    <span class="user-menu-chevron" aria-hidden="true">⌄</span>
                </summary>

                <div class="user-account-dropdown">
                    <div class="user-account-identity">
                        <span class="user-avatar user-avatar-large" aria-hidden="true"><?= e($headerInitial) ?></span>
                        <div>
                            <strong><?= e($headerName) ?></strong>
                            <span><?= e($headerEmail) ?></span>
                        </div>
                    </div>

                    <div class="user-account-meta">
                        <span>⚡ <strong><?= (int) ($headerUser['xp'] ?? 0) ?> XP</strong></span>
                        <?php if (!empty($headerUser['email_verified_at'])): ?>
                            <span class="user-email-ok">✓ E-mail verificado</span>
                        <?php else: ?>
                            <span class="user-email-pending">● E-mail pendente</span>
                        <?php endif; ?>
                    </div>

                    <div class="user-account-links">
                        <a href="<?= url('/perfil') ?>">👤 Meu perfil e evolução</a>
                        <a href="<?= url('/dashboard') ?>">🗺️ Mapa da jornada</a>
                    </div>

                    <form action="<?= url('/logout') ?>" method="POST" class="user-account-logout">
                        <?= csrf_field() ?>
                        <button type="submit">↪ Sair da conta</button>
                    </form>
                </div>
            </details>
        <?php else: ?>
            <a href="<?= url('/login') ?>">Entrar</a>
            <a href="<?= url('/register') ?>" class="btn btn-primary btn-small">Criar conta</a>
        <?php endif; ?>
    </nav>
</div></header>

<main class="container">
    <?php if ($headerIsGuest): ?>
        <div class="guest-banner"><strong>👾 Modo visitante</strong><span>Você avança normalmente durante esta sessão, mas o progresso não é salvo na conta; XP e certificado também não são gerados.</span><a href="<?= url('/register') ?>">Criar conta gratuita</a></div>
    <?php endif; ?>
    <?php if ($flash=\App\Core\Session::flash('success')): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
    <?php if ($flash=\App\Core\Session::flash('error')): ?><div class="alert alert-error"><?= e($flash) ?></div><?php endif; ?>
    <?php require $contentView; ?>
</main>

<footer class="footer"><div class="container"><p>&copy; <?= date('Y') ?> LD Desenvolvimento Web — Plataforma de aprendizado gamificado.</p><span class="footer-status">Sistema online</span></div></footer>
</div>
<script src="<?= asset('js/theme.js') ?>"></script>
<?php if (!empty($lesson)): ?><script src="<?= asset('js/lesson-interactive.js') ?>" defer></script><?php endif; ?>
</body></html>
