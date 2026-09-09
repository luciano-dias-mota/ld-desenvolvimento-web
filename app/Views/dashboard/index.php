<div class="quest-heading">
    <div>
        <span class="page-kicker">Campaign Map</span>
        <h1>Mapa da Jornada</h1>
        <p>
            <?= !empty($isGuest)
                ? 'Avance em ordem: aula, exercício, próxima aula e prova. Seu progresso é temporário nesta sessão.'
                : 'Avance pelas fases, conclua as aulas e libere os próximos desafios.' ?>
        </p>
    </div>
</div>

<?php if (
    empty($isGuest)
    && !empty($emailVerificationEnabled)
    && !empty($user)
    && empty($user['email_verified_at'])
): ?>
    <div class="verification-banner">
        <div>
            <strong>📧 Confirme seu e-mail</strong>
            <p>
                Seu curso e progresso continuam disponíveis. A confirmação deixa sua conta
                pronta para políticas de certificado que exijam e-mail verificado.
            </p>
        </div>
        <form action="<?= url('/verificacao-email/reenviar') ?>" method="POST">
            <?= csrf_field() ?>
            <button class="btn btn-outline btn-small" type="submit">Reenviar confirmação</button>
        </form>
    </div>
<?php endif; ?>

<?php if (empty($courses)): ?>
    <div class="panel center">
        <h2>Nenhum curso publicado</h2>
        <p class="text-muted">Os conteúdos aparecerão aqui assim que forem publicados.</p>
    </div>
<?php endif; ?>

<?php foreach ($courses as $courseData): ?>
    <?php
    $course = $courseData['course'];
    $modules = $courseData['modules'];
    ?>

    <section class="course-section" id="curso-<?= e((string) $course['slug']) ?>">
        <div class="module-header" style="align-items:flex-start;">
            <div>
                <h2><?= e($course['title']) ?></h2>
                <p><?= e($course['description']) ?></p>
            </div>

            <?php if (empty($isGuest)): ?>
                <?php if (!empty($courseData['certificate'])): ?>
                    <a
                        href="<?= url('/certificado/' . rawurlencode($course['slug'])) ?>"
                        class="btn btn-success"
                    >
                        🏆 Ver certificado
                    </a>
                <?php elseif (!empty($courseData['can_issue_certificate'])): ?>
                    <form
                        action="<?= url('/certificado/' . rawurlencode($course['slug']) . '/emitir') ?>"
                        method="POST"
                    >
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-success">🏆 Emitir certificado</button>
                    </form>
                <?php elseif (!empty($courseData['certificate_blocked_by_email'])): ?>
                    <span class="badge">📧 Confirme o e-mail para emitir</span>
                <?php endif; ?>
            <?php else: ?>
                <span class="badge">👾 Sessão sem certificado</span>
            <?php endif; ?>
        </div>

        <?php if (empty($modules)): ?>
            <div class="panel">
                <p class="text-muted mb-0">Este curso ainda não possui módulos publicados.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($modules as $module): ?>
            <?php
            $progressStatus = $module['progress_status'] ?? 'locked';
            $lessonCount = (int) ($module['lessons_count'] ?? 0);
            $lessonCompleted = (int) ($module['lessons_completed'] ?? 0);
            $progressPercent = $lessonCount > 0
                ? min(100, (int) round(($lessonCompleted / $lessonCount) * 100))
                : 0;
            ?>

            <div class="module-card <?= e($progressStatus) ?>">
                <div class="module-header">
                    <h3><?= e($module['title']) ?></h3>

                    <span class="badge">
                        <?php if ($progressStatus === 'completed'): ?>
                            ✓ Concluído
                        <?php elseif ($progressStatus === 'active'): ?>
                            <?= !empty($isGuest) ? '👾 Em andamento' : '⚡ Ativo' ?>
                        <?php else: ?>
                            🔒 Bloqueado
                        <?php endif; ?>
                    </span>
                </div>

                <p><?= e($module['description']) ?></p>

                <div class="module-progress" aria-label="Progresso do módulo">
                    <div
                        class="module-progress-bar"
                        style="width:<?= $progressPercent ?>%"
                    ></div>
                </div>

                <small class="text-muted">
                    <?= $lessonCompleted ?>/<?= $lessonCount ?> aulas concluídas
                    <?= !empty($isGuest) ? ' nesta sessão · não salvo' : '' ?>
                </small>

                <div class="actions-row" style="margin-top:16px;">
                    <?php if ($progressStatus === 'active'): ?>
                        <a
                            href="<?= url(
                                '/cursos/'
                                . rawurlencode($course['slug'])
                                . '/'
                                . rawurlencode($module['slug'])
                            ) ?>"
                            class="btn btn-primary"
                        >
                            <?= $lessonCompleted > 0 ? 'Continuar fase' : 'Entrar na fase' ?>
                        </a>
                    <?php elseif ($progressStatus === 'locked'): ?>
                        <span class="locked">🔒 Complete e seja aprovado na fase anterior</span>
                    <?php else: ?>
                        <a
                            href="<?= url(
                                '/cursos/'
                                . rawurlencode($course['slug'])
                                . '/'
                                . rawurlencode($module['slug'])
                            ) ?>"
                            class="btn btn-outline"
                        >
                            Revisar fase
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?>
