<h1>Mapa da Jornada</h1>

<?php foreach ($courses as $courseData): ?>
    <?php $course = $courseData['course']; ?>
    <?php $modules = $courseData['modules']; ?>
    <section class="course-section">
        <h2><?= e($course['title']) ?></h2>
        <p><?= e($course['description']) ?></p>

        <?php foreach ($modules as $module): ?>
            <div class="module-card <?= $module['status'] ?>">
                <div class="module-header">
                    <h3><?= e($module['title']) ?></h3>
                    <span class="badge"><?= $module['status'] === 'active' ? 'Ativo' : ($module['status'] === 'completed' ? 'Concluído' : 'Bloqueado') ?></span>
                </div>
                <p><?= e($module['description']) ?></p>
                <div class="module-progress">
                    <!-- Progresso simplificado -->
                </div>
                <?php if ($module['status'] === 'active'): ?>
                    <a href="<?= url('/cursos/' . $course['slug'] . '/' . $module['slug']) ?>" class="btn btn-primary">Entrar</a>
                <?php elseif ($module['status'] === 'locked'): ?>
                    <span class="locked">🔒</span>
                <?php else: ?>
                    <span class="completed">✅ Concluído</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?>