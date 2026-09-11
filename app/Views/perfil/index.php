<?php
$name = trim((string) ($profile['name'] ?? 'Aluno'));
$email = trim((string) ($profile['email'] ?? ''));
$initial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($name !== '' ? $name : 'A', 0, 1))
    : strtoupper(substr($name !== '' ? $name : 'A', 0, 1));

$createdAt = !empty($profile['created_at']) ? strtotime((string) $profile['created_at']) : false;
$memberSince = $createdAt ? date('d/m/Y', $createdAt) : '—';

$completedLessons = (int) ($summary['completed_lessons'] ?? 0);
$totalLessons = (int) ($summary['total_lessons'] ?? 0);
$completedModules = (int) ($summary['completed_modules'] ?? 0);
$totalModules = (int) ($summary['total_modules'] ?? 0);
$percent = max(0, min(100, (int) ($summary['percent'] ?? 0)));
$courses = $summary['courses'] ?? [];
?>

<div class="profile-page">
    <div class="breadcrumb profile-breadcrumb">
        <a href="<?= url('/dashboard') ?>">Mapa da Jornada</a>
        <span>/</span>
        <span>Meu perfil</span>
    </div>

    <section class="profile-hero panel">
        <div class="profile-identity">
            <span class="profile-avatar" aria-hidden="true"><?= e($initial) ?></span>

            <div class="profile-identity-copy">
                <span class="page-kicker">Player Profile</span>
                <h1><?= e($name) ?></h1>
                <p class="profile-email"><?= e($email) ?></p>

                <div class="profile-badges">
                    <?php if (!empty($profile['email_verified_at'])): ?>
                        <span class="profile-badge success">✓ E-mail verificado</span>
                    <?php else: ?>
                        <span class="profile-badge warning">● E-mail não verificado</span>
                    <?php endif; ?>

                    <span class="profile-badge">
                        <?= ($profile['role'] ?? '') === 'admin' ? 'Administrador' : 'Aluno' ?>
                    </span>

                    <span class="profile-badge">Desde <?= e($memberSince) ?></span>
                </div>
            </div>
        </div>

        <div class="profile-xp" aria-label="XP acumulado">
            <span>⚡</span>
            <div>
                <small>XP acumulado</small>
                <strong><?= (int) ($profile['xp'] ?? 0) ?></strong>
            </div>
        </div>
    </section>

    <?php if (empty($profile['email_verified_at']) && !empty($emailVerificationEnabled)): ?>
        <div class="verification-banner profile-verification-banner">
            <div>
                <strong>📧 Confirme seu e-mail</strong>
                <p>Use a confirmação para deixar a conta pronta para recursos que exijam endereço verificado.</p>
            </div>

            <form action="<?= url('/verificacao-email/reenviar') ?>" method="POST">
                <?= csrf_field() ?>
                <button class="btn btn-outline btn-small" type="submit">Reenviar confirmação</button>
            </form>
        </div>
    <?php endif; ?>

    <section class="profile-grid" aria-label="Resumo do progresso">
        <div class="profile-stat panel">
            <span class="profile-stat-icon">📚</span>
            <div>
                <span>Aulas concluídas</span>
                <strong><?= $completedLessons ?> <small>/ <?= $totalLessons ?></small></strong>
            </div>
        </div>

        <div class="profile-stat panel">
            <span class="profile-stat-icon">🧩</span>
            <div>
                <span>Módulos concluídos</span>
                <strong><?= $completedModules ?> <small>/ <?= $totalModules ?></small></strong>
            </div>
        </div>

        <div class="profile-stat panel profile-stat-highlight">
            <span class="profile-stat-icon">📈</span>
            <div>
                <span>Progresso geral</span>
                <strong><?= $percent ?>%</strong>
            </div>
        </div>
    </section>

    <section class="profile-section profile-evolution panel">
        <div class="profile-section-heading">
            <div>
                <span class="page-kicker">Evolution</span>
                <h2>Sua evolução no curso</h2>
                <p><?= $completedLessons ?> de <?= $totalLessons ?> aulas concluídas</p>
            </div>

            <strong class="profile-section-percent"><?= $percent ?>%</strong>
        </div>

        <div
            class="profile-progress profile-progress-main"
            role="progressbar"
            aria-valuenow="<?= $percent ?>"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <span style="width:<?= $percent ?>%"></span>
        </div>

        <?php if (empty($courses)): ?>
            <p class="text-muted profile-empty">Ainda não há cursos publicados para calcular o progresso.</p>
        <?php endif; ?>

        <?php foreach ($courses as $course): ?>
            <?php $coursePercent = max(0, min(100, (int) ($course['percent'] ?? 0))); ?>

            <article class="profile-course">
                <div class="profile-course-heading">
                    <div>
                        <h3><?= e((string) ($course['title'] ?? 'Curso')) ?></h3>
                        <span>
                            <?= (int) ($course['lessons_completed'] ?? 0) ?> de
                            <?= (int) ($course['lessons_count'] ?? 0) ?> aulas
                        </span>
                    </div>

                    <strong><?= $coursePercent ?>%</strong>
                </div>

                <div class="profile-progress profile-progress-course">
                    <span style="width:<?= $coursePercent ?>%"></span>
                </div>

                <div class="profile-modules">
                    <?php foreach (($course['modules'] ?? []) as $module): ?>
                        <?php $modulePercent = max(0, min(100, (int) ($module['percent'] ?? 0))); ?>

                        <div class="profile-module-row">
                            <div class="profile-module-title">
                                <span class="profile-module-number"><?= (int) ($module['module_number'] ?? 0) ?></span>

                                <div>
                                    <strong><?= e((string) ($module['title'] ?? 'Módulo')) ?></strong>
                                    <small>
                                        <?= (int) ($module['lessons_completed'] ?? 0) ?>/<?= (int) ($module['lessons_count'] ?? 0) ?> aulas
                                        <?= !empty($module['completed']) ? ' · prova concluída' : '' ?>
                                    </small>
                                </div>
                            </div>

                            <div class="profile-module-progress">
                                <div class="profile-progress">
                                    <span style="width:<?= $modulePercent ?>%"></span>
                                </div>
                                <strong><?= $modulePercent ?>%</strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="profile-two-columns">
        <div class="profile-section panel profile-account-card">
            <span class="page-kicker">Account</span>
            <h2>Dados da conta</h2>

            <dl class="profile-details">
                <div>
                    <dt>Nome</dt>
                    <dd><?= e($name) ?></dd>
                </div>

                <div>
                    <dt>E-mail</dt>
                    <dd><?= e($email) ?></dd>
                </div>

                <div>
                    <dt>Perfil</dt>
                    <dd><?= ($profile['role'] ?? '') === 'admin' ? 'Administrador' : 'Aluno' ?></dd>
                </div>

                <div>
                    <dt>Cadastro</dt>
                    <dd><?= e($memberSince) ?></dd>
                </div>
            </dl>
        </div>

        <div class="profile-section panel profile-access-card">
            <span class="page-kicker">Access</span>
            <h2>Métodos de acesso</h2>

            <div class="access-methods">
                <div class="access-method <?= !empty($profile['has_google']) ? 'enabled' : '' ?>">
                    <span class="access-method-icon">G</span>

                    <div>
                        <strong>Conta Google</strong>
                        <small><?= !empty($profile['has_google']) ? 'Conectada a esta conta' : 'Não conectada' ?></small>
                    </div>

                    <span class="access-method-status"><?= !empty($profile['has_google']) ? '✓' : '—' ?></span>
                </div>

                <div class="access-method <?= !empty($profile['has_password']) ? 'enabled' : '' ?>">
                    <span class="access-method-icon">🔑</span>

                    <div>
                        <strong>E-mail e senha</strong>
                        <small><?= !empty($profile['has_password']) ? 'Senha local configurada' : 'Conta sem senha local' ?></small>
                    </div>

                    <span class="access-method-status"><?= !empty($profile['has_password']) ? '✓' : '—' ?></span>
                </div>
            </div>

            <?php if (!empty($profile['has_google']) && empty($profile['has_password'])): ?>
                <p class="profile-help">
                    Você entrou pelo Google. A recuperação de senha pode criar uma senha local sem remover o acesso Google.
                </p>
            <?php endif; ?>
        </div>
    </section>
</div>
