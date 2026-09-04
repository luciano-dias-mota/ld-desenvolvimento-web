<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'PHP Quest') ?></title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
    <div class="auth-shell">
        <?php if ($flash = Session::flash('success')): ?>
            <div class="alert alert-success"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($flash = Session::flash('error')): ?>
            <div class="alert alert-error"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php require $contentView; ?>
    </div>
</body>
</html>