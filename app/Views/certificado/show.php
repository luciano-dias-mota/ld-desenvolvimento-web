<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado &middot; CodeQuest Platform</title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<div class="container" style="padding-top:20px;">
    <a href="<?= url('/dashboard') ?>" class="logo"><span class="spark">&lt;/&gt;</span> CodeQuest</a>
</div>

<div class="certificate">
    <p class="text-muted" style="letter-spacing:.15em;">CERTIFICADO DE CONCLUSÃO</p>
    <h1 style="margin:18px 0;">Curso de <?= e($course['title']) ?></h1>
    <p style="font-size:1.2rem; color:var(--text);">
        Certificamos que <strong><?= e($user['name']) ?></strong> concluiu com sucesso
        todas as fases e provas do curso de <?= e($course['title']) ?> na CodeQuest Platform.
    </p>
    <p class="code" style="margin-top:30px;">
        Código de validação: <?= e($certificate['certificate_code']) ?><br>
        Emitido em <?= (new DateTime($certificate['issued_at']))->format('d/m/Y') ?>
    </p>
</div>

<div class="container" style="text-align:center; padding-bottom:60px;">
    <button onclick="window.print()" class="btn btn-primary">Imprimir / salvar em PDF</button>
</div>
</body>
</html>