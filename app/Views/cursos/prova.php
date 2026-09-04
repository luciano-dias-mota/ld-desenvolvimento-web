<div class="breadcrumb">
    <a href="<?= url('/dashboard') ?>">Mapa da Jornada</a> /
    <a href="<?= url('/cursos/' . $course['slug'] . '/' . $module['slug']) ?>"><?= e($module['title']) ?></a>
</div>

<h1><?= e($test['title']) ?></h1>
<p>Fase: <?= e($module['title']) ?> &mdash; você precisa de <?= (int) $test['passing_score'] ?>% de acertos para avançar.</p>

<?php if (!empty($resultado)): ?>
    <div class="result-banner <?= $passed ? '' : 'is-wrong' ?>">
        <?= $passed
            ? "✓ Aprovado com {$score}%! Próxima fase liberada."
            : "✗ Você fez {$score}%. Precisa de " . (int) $test['passing_score'] . "% para passar. Revise as aulas e tente de novo." ?>
    </div>

    <div style="display:flex; gap:14px; margin-bottom:40px;">
        <?php if ($passed): ?>
            <a href="<?= url('/dashboard') ?>" class="btn btn-success">Voltar ao mapa da jornada</a>
        <?php else: ?>
            <a href="<?= url('/cursos/' . $course['slug'] . '/' . $module['slug'] . '/prova') ?>" class="btn btn-primary">Tentar novamente</a>
        <?php endif; ?>
    </div>
<?php else: ?>

<form action="<?= url('/cursos/' . $course['slug'] . '/' . $module['slug'] . '/prova') ?>" method="POST">
    <?= csrf_field() ?>

    <?php foreach ($questions as $i => $q): ?>
        <div class="panel" style="margin-bottom:18px;">
            <h3 style="margin-bottom:14px;"><?= $i + 1 ?>. <?= e($q['question']) ?></h3>
            <?php $options = json_decode($q['options'], true); ?>
            <?php foreach ($options as $letra => $texto): ?>
                <label class="quiz-option">
                    <input type="radio" name="respostas[<?= (int) $q['id'] ?>]" value="<?= e($letra) ?>" required>
                    <span><strong><?= e(strtoupper($letra)) ?>)</strong> <?= e($texto) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-success" style="margin-bottom:60px;">Enviar prova</button>
</form>

<?php endif; ?>