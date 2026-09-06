<div class="breadcrumb">
    <a href="<?= url('/dashboard') ?>">Mapa da Jornada</a>
    <span>/</span>
    <span><?= e($module['title']) ?></span>
</div>

<div class="page-heading">
    <span class="page-kicker">Mission Stage</span>
    <h1><?= e($module['title']) ?></h1>
    <p><?= e($module['description']) ?></p>
</div>

<div class="lesson-list">
    <?php foreach ($lessons as $lesson): ?>
        <div class="lesson-row <?= $lesson['completed'] ? 'completed' : '' ?>">
            <div class="title">
                <span class="lesson-icon"><?= $lesson['completed'] ? '✓' : '&gt;_' ?></span>
                <?= e($lesson['title']) ?>
            </div>

            <div class="actions">
                <a href="<?= url('/aulas/' . $course['slug'] . '/' . $module['slug'] . '/' . $lesson['slug']) ?>" class="btn btn-outline btn-small">
                    <?= $lesson['completed'] ? 'Revisar aula' : 'Abrir aula' ?>
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
$allCompleted = true;
foreach ($lessons as $lesson) {
    if (!$lesson['completed']) {
        $allCompleted = false;
        break;
    }
}
?>

<?php if ($allCompleted): ?>
    <div class="actions-row" style="margin-top:24px;">
        <a href="<?= url('/cursos/' . $course['slug'] . '/' . $module['slug'] . '/prova') ?>" class="btn btn-success">⚔ Fazer prova do módulo</a>
    </div>
<?php endif; ?>
