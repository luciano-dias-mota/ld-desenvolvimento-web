<?php $this->layout('main'); ?>

<div class="breadcrumb">
    <a href="<?= url('/dashboard?curso=' . $course['slug']) ?>">Mapa da Jornada</a>
    <span>/</span>
    <a href="<?= url('/aulas/' . $course['slug'] . '/' . $module['slug'] . '/' . $lesson['slug']) ?>"><?= e($lesson['title']) ?></a>
</div>

<div class="page-heading">
    <span class="page-kicker">Challenge Mode</span>
    <h1>Exercício de fixação</h1>
    <p><?= e($exercise['title']) ?></p>
</div>

<?php if (isset($isCorrect)): ?>
    <div class="result-banner <?= $isCorrect ? '' : 'is-wrong' ?>">
        <?= $isCorrect
            ? '✓ Resposta certa! Aula marcada como concluída. +' . (int) $exercise['xp_reward'] . ' XP.'
            : '✗ Não foi dessa vez. Revise a aula e tente novamente.' ?>
    </div>
<?php endif; ?>

<div class="panel" style="max-width:78ch;">
    <p style="margin-bottom:20px;"><?= e($exercise['instructions']) ?></p>

    <form action="<?= url('/exercicios/' . $course['slug'] . '/' . $module['slug'] . '/' . $lesson['slug']) ?>" method="POST">
        <?= csrf_field() ?>

        <?php if ($exercise['exercise_type'] === 'multiple_choice' || $exercise['exercise_type'] === 'true_false'): ?>
            <?php foreach ($options as $letra => $texto): ?>
                <label class="quiz-option">
                    <input type="radio" name="resposta" value="<?= e($letra) ?>" <?= (isset($answer) && $answer === $letra) ? 'checked' : '' ?> required>
                    <span><strong><?= e(strtoupper($letra)) ?>)</strong> <?= e($texto) ?></span>
                </label>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="field">
                <label for="resposta">Sua resposta</label>
                <textarea id="resposta" name="resposta" rows="6" class="code-input" required><?= isset($answer) ? e($answer) : '' ?></textarea>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Enviar resposta</button>
    </form>
</div>
