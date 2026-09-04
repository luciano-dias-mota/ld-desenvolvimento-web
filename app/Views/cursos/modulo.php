<div class="breadcrumb">
    <a href="<?= url('/dashboard') ?>">Mapa da Jornada</a> / <?= e($module['title']) ?>
</div>

<h1><?= e($module['title']) ?></h1>
<p><?= e($module['description']) ?></p>

<div class="lesson-list">
    <?php foreach ($lessons as $lesson): ?>
        <div class="lesson-row <?= $lesson['completed'] ? 'completed' : '' ?>">
            <div class="title">
                <?= $lesson['completed'] ? '✅' : '📘' ?> <?= e($lesson['title']) ?>
            </div>
            <div class="actions">
                <a href="<?= url('/aulas/' . $course['slug'] . '/' . $module['slug'] . '/' . $lesson['slug']) ?>" class="btn btn-outline">Ver aula</a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Link para a prova, se todas as aulas estiverem concluídas -->
<?php
$allCompleted = true;
foreach ($lessons as $lesson) {
    if (!$lesson['completed']) {
        $allCompleted = false;
        break;
    }
}
if ($allCompleted): ?>
    <a href="<?= url('/cursos/' . $course['slug'] . '/' . $module['slug'] . '/prova') ?>" class="btn btn-success">Fazer prova do módulo</a>
<?php endif; ?>