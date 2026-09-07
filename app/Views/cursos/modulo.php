<div class="breadcrumb"><a href="<?= url('/dashboard') ?>">Mapa da Jornada</a><span>/</span><span><?= e($module['title']) ?></span></div>
<div class="page-heading"><span class="page-kicker">Mission Stage</span><h1><?= e($module['title']) ?></h1><p><?= e($module['description']) ?></p></div>
<?php if(!empty($isGuest)):?><div class="guest-inline-note">👾 Todas as aulas e a prova estão liberadas para exploração. Nada desta sessão será gravado.</div><?php endif;?>
<div class="lesson-list">
<?php foreach($lessons as $lesson):?><div class="lesson-row <?= $lesson['completed']?'completed':'' ?>"><div class="title"><span class="lesson-icon"><?= $lesson['completed']?'✓':'&gt;_' ?></span><?= e($lesson['title']) ?></div><div class="actions"><a href="<?= url('/aulas/'.rawurlencode($course['slug']).'/'.rawurlencode($module['slug']).'/'.rawurlencode($lesson['slug'])) ?>" class="btn btn-outline btn-small"><?= !empty($isGuest)?'Abrir aula':($lesson['completed']?'Revisar aula':'Abrir aula') ?></a></div></div><?php endforeach;?>
</div>
<?php $allCompleted=!empty($lessons);foreach($lessons as $lesson){if(!$lesson['completed']){$allCompleted=false;break;}} ?>
<?php if(!empty($isGuest)||$allCompleted):?><div class="actions-row" style="margin-top:24px;"><a href="<?= url('/cursos/'.rawurlencode($course['slug']).'/'.rawurlencode($module['slug']).'/prova') ?>" class="btn btn-success">⚔ <?= !empty($isGuest)?'Testar conhecimentos':'Fazer prova do módulo' ?></a></div><?php endif;?>
