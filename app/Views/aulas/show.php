<?php $this->layout('main'); ?>

<div class="breadcrumb">
    <a href="<?= url('/dashboard?curso=' . $course['slug']) ?>">Mapa da Jornada</a> /
    <a href="<?= url('/cursos/' . $course['slug'] . '/' . $module['slug']) ?>"><?= e($module['title']) ?></a>
</div>

<h1><?= e($lesson['title']) ?></h1>

<?php if ($completed): ?>
    <span class="badge-check">✓ Aula concluída</span>
<?php endif; ?>

<?php if (!empty($lesson['video_url'])): ?>
    <div class="lesson-content" style="padding:0; overflow:hidden;">
        <div style="position:relative; padding-top:56.25%;">
            <iframe src="<?= e($lesson['video_url']) ?>"
                    style="position:absolute; top:0; left:0; width:100%; height:100%; border:0;"
                    allowfullscreen loading="lazy"></iframe>
        </div>
    </div>
<?php endif; ?>

<div class="lesson-content">
    <?= $lesson['content'] ?? '<p>Conteúdo desta aula em breve.</p>' ?>
</div>

<div style="display:flex; gap:14px; flex-wrap:wrap; margin-bottom:60px;">
    <?php if ($exercise): ?>
        <a href="<?= url('/exercicios/' . $course['slug'] . '/' . $module['slug'] . '/' . $lesson['slug']) ?>"
           class="btn btn-primary">
            <?= $completed ? 'Rever exercício' : 'Ir para o exercício de fixação' ?>
        </a>
    <?php elseif (!$completed): ?>
        <form action="<?= url('/aulas/' . $course['slug'] . '/' . $module['slug'] . '/' . $lesson['slug'] . '/concluir') ?>" method="POST">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">Marcar aula como concluída</button>
        </form>
    <?php endif; ?>

    <?php if ($next): ?>
        <a href="<?= url('/aulas/' . $course['slug'] . '/' . $module['slug'] . '/' . $next['slug']) ?>" class="btn btn-outline">
            Próxima aula
        </a>
    <?php endif; ?>
</div>