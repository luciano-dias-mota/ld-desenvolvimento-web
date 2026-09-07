<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado · LD Desenvolvimento Web</title>

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
    <div class="certificate-shell">
        <div class="container certificate-toolbar">
            <div class="actions-row" style="justify-content:space-between;">
                <a href="<?= url('/dashboard') ?>" class="logo">
                    <span class="logo-mark">&lt;/&gt;</span>
                    <span class="logo-word"><strong>LD</strong> Desenvolvimento Web</span>
                </a>

                <button type="button" class="theme-toggle" data-theme-toggle aria-label="Alternar tema">
                    <span class="theme-toggle-icon" data-theme-icon>☀</span>
                    <span class="theme-toggle-label" data-theme-label>Claro</span>
                </button>
            </div>
        </div>

        <div class="certificate">
            <p class="certificate-title">CERTIFICADO DE CONCLUSÃO</p>
            <h1 style="margin:18px 0;">Curso de <?= e($course['title']) ?></h1>
            <p style="font-size:1.15rem; margin-inline:auto;">
                Certificamos que <strong><?= e($user['name']) ?></strong> concluiu com sucesso
                todas as fases e provas do curso de <?= e($course['title']) ?> na plataforma LD Desenvolvimento Web.
            </p>
            <p class="code">
                Código de validação: <?= e($certificate['certificate_code']) ?><br>
                Emitido em <?= (new DateTime($certificate['issued_at']))->format('d/m/Y') ?>
            </p>
        </div>

        <div class="container certificate-toolbar center">
            <button onclick="window.print()" class="btn btn-primary">Imprimir / salvar em PDF</button>
        </div>
    </div>

    <script src="<?= asset('js/theme.js') ?>"></script>
</body>
</html>
