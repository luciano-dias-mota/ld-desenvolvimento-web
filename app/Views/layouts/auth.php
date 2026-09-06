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
    <div class="auth-page">
        <div class="auth-topbar">
            <a href="<?= url('/') ?>" class="logo" aria-label="PHP Quest - início">
                <span class="logo-mark">&lt;/&gt;</span>
                <span class="logo-word"><strong>PHP</strong> Quest</span>
            </a>

            <button type="button" class="theme-toggle" data-theme-toggle aria-label="Alternar tema">
                <span class="theme-toggle-icon" data-theme-icon>☀</span>
                <span class="theme-toggle-label" data-theme-label>Claro</span>
            </button>
        </div>

        <main class="auth-shell">
            <div class="auth-stage">
                <section class="auth-intro" aria-hidden="true">
                    <span class="eyebrow">Learning Mode</span>
                    <h2>Evolua seu código. <span>Suba de nível.</span></h2>
                    <p>Aprenda programação em uma jornada prática, com fases, exercícios e progresso visível.</p>
                    <div class="tech-chips">
                        <span class="tech-chip">PHP</span>
                        <span class="tech-chip">Lógica</span>
                        <span class="tech-chip">Banco de Dados</span>
                        <span class="tech-chip">Desafios</span>
                    </div>
                </section>

                <section class="auth-content">
                    <div class="auth-flashes">
                        <?php if ($flash = \App\Core\Session::flash('success')): ?>
                            <div class="alert alert-success"><?= e($flash) ?></div>
                        <?php endif; ?>

                        <?php if ($flash = \App\Core\Session::flash('error')): ?>
                            <div class="alert alert-error"><?= e($flash) ?></div>
                        <?php endif; ?>
                    </div>

                    <?php require $contentView; ?>
                </section>
            </div>
        </main>
    </div>

    <script src="<?= asset('js/theme.js') ?>"></script>
</body>
</html>
