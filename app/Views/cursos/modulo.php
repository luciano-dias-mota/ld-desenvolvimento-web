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

<?php if (!empty($isGuest)): ?>
    <div class="guest-inline-note">
        👾 Modo visitante: avance em ordem. O progresso desta jornada existe somente nesta sessão
        e não gera XP permanente nem certificado.
    </div>
<?php endif; ?>

<div class="lesson-list">
    <?php foreach ($lessons as $lesson): ?>
        <?php
        $completed = !empty($lesson['completed']);
        $locked = !empty($lesson['locked']);
        ?>
        <div class="lesson-row <?= $completed ? 'completed' : '' ?> <?= $locked ? 'locked' : '' ?>">
            <div class="title">
                <span class="lesson-icon">
                    <?= $completed ? '✓' : ($locked ? '🔒' : '&gt;_') ?>
                </span>
                <?= e($lesson['title']) ?>
            </div>

            <div class="actions">
                <?php if ($locked): ?>
                    <span class="text-muted">Conclua a aula anterior</span>
                <?php else: ?>
                    <a
                        href="<?= url(
                            '/aulas/'
                            . rawurlencode($course['slug'])
                            . '/'
                            . rawurlencode($module['slug'])
                            . '/'
                            . rawurlencode($lesson['slug'])
                        ) ?>"
                        class="btn btn-outline btn-small"
                    >
                        <?= $completed ? 'Revisar aula' : 'Abrir aula' ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
$allCompleted = !empty($lessons);
foreach ($lessons as $lesson) {
    if (empty($lesson['completed'])) {
        $allCompleted = false;
        break;
    }
}
?>

<?php if ($allCompleted): ?>
    <div class="actions-row" style="margin-top:24px;">
        <?php if (($module['progress_status'] ?? '') === 'completed' && empty($isGuest)): ?>
            <span class="badge-check">✓ Prova do módulo concluída</span>
        <?php else: ?>
            <a
                href="<?= url(
                    '/cursos/'
                    . rawurlencode($course['slug'])
                    . '/'
                    . rawurlencode($module['slug'])
                    . '/prova'
                ) ?>"
                class="btn btn-success"
            >
                ⚔ <?= ($module['progress_status'] ?? '') === 'completed' ? 'Refazer prova do módulo' : 'Fazer prova do módulo' ?>
            </a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="guest-inline-note" style="margin-top:24px;">
        🔒 A prova do módulo será liberada depois que todas as aulas forem concluídas.
    </div>
<?php endif; ?>
