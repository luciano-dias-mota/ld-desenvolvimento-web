<h1>Painel administrativo</h1>
<p>Visão geral da plataforma. O CRUD completo de cursos/módulos/aulas é o próximo passo natural aqui.</p>

<div class="journey-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom:40px;">
    <div class="panel"><h3><?= (int) $stats['usuarios'] ?></h3><p class="mb-0">Alunos</p></div>
    <div class="panel"><h3><?= (int) $stats['cursos'] ?></h3><p class="mb-0">Cursos</p></div>
    <div class="panel"><h3><?= (int) $stats['modulos'] ?></h3><p class="mb-0">Módulos</p></div>
    <div class="panel"><h3><?= (int) $stats['aulas'] ?></h3><p class="mb-0">Aulas</p></div>
</div>

<h2>Cursos cadastrados</h2>
<div class="lesson-list">
    <?php foreach ($courses as $c): ?>
        <div class="lesson-row">
            <div class="title">
                <strong><?= e($c['title']) ?></strong>
                <br><small><?= $c['status'] === 'published' ? 'Publicado' : 'Rascunho' ?> &middot; slug: <?= e($c['slug']) ?></small>
            </div>
        </div>
    <?php endforeach; ?>
</div>