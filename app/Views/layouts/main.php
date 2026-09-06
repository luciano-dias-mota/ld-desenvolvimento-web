<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#07090c">
    <title><?= e($title ?? 'PHP Quest') ?></title>

    <script>
        (function () {
            try {
                const saved = localStorage.getItem('phpquest-theme');
                document.documentElement.setAttribute('data-theme', saved === 'light' ? 'light' : 'dark');
            } catch (e) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
    <div class="app">
        <header class="header">
            <div class="container">
                <a href="<?= url('/dashboard') ?>" class="logo" aria-label="PHP Quest - Mapa da Jornada">
                    <span class="logo-mark">&lt;/&gt;</span>
                    <span class="logo-word"><strong>PHP</strong> Quest</span>
                </a>

                <nav aria-label="Navegação principal">
                    <a href="<?= url('/dashboard') ?>">Mapa</a>

                    <?php if (\App\Core\Auth::isAdmin()): ?>
                        <a href="<?= url('/admin/dashboard') ?>">Admin</a>
                    <?php endif; ?>

                    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Alternar tema">
                        <span class="theme-toggle-icon" data-theme-icon>☀</span>
                        <span class="theme-toggle-label" data-theme-label>Claro</span>
                    </button>

                    <form action="<?= url('/logout') ?>" method="POST" class="header-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline btn-small">Sair</button>
                    </form>
                </nav>
            </div>
        </header>

        <main class="container">
            <?php if ($flash = \App\Core\Session::flash('success')): ?>
                <div class="alert alert-success"><?= e($flash) ?></div>
            <?php endif; ?>

            <?php if ($flash = \App\Core\Session::flash('error')): ?>
                <div class="alert alert-error"><?= e($flash) ?></div>
            <?php endif; ?>

            <?php require $contentView; ?>
        </main>

        <footer class="footer">
            <div class="container">
                <p>&copy; <?= date('Y') ?> PHP Quest — Plataforma de aprendizado gamificado.</p>
                <span class="footer-status">Sistema online</span>
            </div>
        </footer>
    </div>

    <script src="<?= asset('js/theme.js') ?>"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
