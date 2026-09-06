<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página não encontrada · PHP Quest</title>

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
    <div class="error-page">
        <div class="error-box">
            <span class="eyebrow">Map Error</span>
            <h1 class="error-code">404</h1>
            <h2>Fase não encontrada</h2>
            <p>Essa rota ainda não existe no mapa da jornada. Retorne ao painel para continuar sua evolução.</p>
            <a href="<?= url('/dashboard') ?>" class="btn btn-primary">Voltar ao mapa da jornada</a>
        </div>
    </div>

    <script src="<?= asset('js/theme.js') ?>"></script>
</body>
</html>
