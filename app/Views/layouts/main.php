<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'PHP Quest') ?></title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app">
        <header class="header">
            <div class="container">
                <a href="<?= url('/dashboard') ?>" class="logo"><span class="spark">&lt;/&gt;</span> PHP Quest</a>
                <nav>
                    <a href="<?= url('/dashboard') ?>">Mapa</a>
                    <?php if (\App\Core\Auth::isAdmin()): ?>
                        <a href="<?= url('/admin/dashboard') ?>">Admin</a>
                    <?php endif; ?>
                    <form action="<?= url('/logout') ?>" method="POST" style="display:inline;">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline" style="padding:4px 12px;">Sair</button>
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
                <p>&copy; <?= date('Y') ?> PHP Quest - Plataforma de aprendizado gamificado</p>
            </div>
        </footer>
    </div>
    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>