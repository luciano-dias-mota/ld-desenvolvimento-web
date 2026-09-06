<div class="breadcrumb">
    <a href="<?= url('/dashboard') ?>">Mapa da Jornada</a>
    <span>/</span>
    <a href="<?= url('/cursos/' . $course['slug'] . '/' . $module['slug']) ?>"><?= e($module['title']) ?></a>
</div>

<div class="page-heading">
    <span class="page-kicker">Boss Challenge</span>
    <h1><?= e($test['title']) ?></h1>
    <p>Fase: <?= e($module['title']) ?> — você precisa de <strong><?= (int) $test['passing_score'] ?>%</strong> de acertos para avançar.</p>
</div>

<?php if (!empty($resultado)): ?>
    <div class="result-banner <?= $passed ? '' : 'is-wrong' ?>">
        <?= $passed
            ? "✓ Aprovado com {$score}%! Próxima fase liberada."
            : "✗ Você fez {$score}%. Precisa de " . (int) $test['passing_score'] . "% para passar. Revise as aulas e tente de novo." ?>
    </div>

    <div class="actions-row" style="margin-bottom:40px;">
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
        <div class="panel question-card">
            <h3><span class="question-number"><?= $i + 1 ?>.</span> <?= e($q['question']) ?></h3>
            <?php $options = json_decode($q['options'], true); ?>

            <?php foreach ($options as $letra => $texto): ?>
                <label class="quiz-option">
                    <input type="radio" name="respostas[<?= (int) $q['id'] ?>]" value="<?= e($letra) ?>" required>
                    <span><strong><?= e(strtoupper($letra)) ?>)</strong> <?= e($texto) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-success" style="margin-bottom:60px;">Finalizar prova</button>
</form>

<?php endif; ?>
