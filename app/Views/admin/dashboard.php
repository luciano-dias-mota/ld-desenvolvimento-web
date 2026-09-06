<div class="page-heading">
    <span class="page-kicker">Control Center</span>
    <h1>Painel administrativo</h1>
    <p>Visão geral da plataforma e dos conteúdos cadastrados.</p>
</div>

<div class="stats-grid">
    <div class="panel stat-card">
        <span class="eyebrow">Players</span>
        <h3><?= (int) $stats['usuarios'] ?></h3>
        <p class="mb-0 stat-label">Alunos</p>
    </div>

    <div class="panel stat-card">
        <span class="eyebrow">Tracks</span>
        <h3><?= (int) $stats['cursos'] ?></h3>
        <p class="mb-0 stat-label">Cursos</p>
    </div>

    <div class="panel stat-card">
        <span class="eyebrow">Stages</span>
        <h3><?= (int) $stats['modulos'] ?></h3>
        <p class="mb-0 stat-label">Módulos</p>
    </div>

    <div class="panel stat-card">
        <span class="eyebrow">Missions</span>
        <h3><?= (int) $stats['aulas'] ?></h3>
        <p class="mb-0 stat-label">Aulas</p>
    </div>
</div>

<h2>Cursos cadastrados</h2>
<div class="lesson-list">
    <?php foreach ($courses as $c): ?>
        <div class="lesson-row">
            <div class="title">
                <strong><?= e($c['title']) ?></strong>
                <br>
                <small class="text-muted"><?= $c['status'] === 'published' ? 'Publicado' : 'Rascunho' ?> · slug: <?= e($c['slug']) ?></small>
            </div>
        </div>
    <?php endforeach; ?>
</div>
