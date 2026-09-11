<?php
$name = trim((string) ($profile['name'] ?? 'Aluno'));
$email = trim((string) ($profile['email'] ?? ''));
$initial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($name !== '' ? $name : 'A', 0, 1))
    : strtoupper(substr($name !== '' ? $name : 'A', 0, 1));

$createdAt = !empty($profile['created_at']) ? strtotime((string) $profile['created_at']) : false;
$memberSince = $createdAt ? date('d/m/Y', $createdAt) : '—';
?>
<div class="profile-page">
    <div class="breadcrumb">
        <a href="<?= url('/dashboard') ?>">Mapa da Jornada</a>
        <span>/</span>
        <span>Meu perfil</span>
    </div>

    <section class="profile-hero panel">
        <div class="profile-identity">
            <span class="profile-avatar"><?= e($initial) ?></span>
            <div>
                <span class="page-kicker">Player Profile</span>
                <h1><?= e($name) ?></h1>
                <p><?= e($email) ?></p>
                <div class="profile-badges">
                    <?php if (!empty($profile['email_verified_at'])): ?>
                        <span class="profile-badge success">✓ E-mail verificado</span>
                    <?php else: ?>
                        <span class="profile-badge warning">● E-mail não verificado</span>
                    <?php endif; ?>
                    <span class="profile-badge"><?= ($profile['role'] ?? '') === 'admin' ? 'Admin' : 'Aluno' ?></span>
                    <span class="profile-badge">Membro desde <?= e($memberSince) ?></span>
                </div>
            </div>
        </div>

        <div class="profile-xp">
            <small>XP acumulado</small>
            <strong>⚡ <?= (int) ($profile['xp'] ?? 0) ?></strong>
        </div>
    </section>

    <?php if (empty($profile['email_verified_at']) && !empty($emailVerificationEnabled)): ?>
        <div class="verification-banner">
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

    <section class="profile-grid">
        <div class="profile-stat panel">
            <span>Aulas concluídas</span>
            <strong><?= (int) $summary['completed_lessons'] ?> <small>/ <?= (int) $summary['total_lessons'] ?></small></strong>
        </div>
        <div class="profile-stat panel">
            <span>Módulos concluídos</span>
            <strong><?= (int) $summary['completed_modules'] ?> <small>/ <?= (int) $summary['total_modules'] ?></small></strong>
        </div>
        <div class="profile-stat panel">
            <span>Progresso geral</span>
            <strong><?= (int) $summary['percent'] ?>%</strong>
        </div>
    </section>

    <section class="profile-section panel">
        <div class="profile-section-heading">
            <div>
                <span class="page-kicker">Evolution</span>
                <h2>Sua evolução no curso</h2>
            </div>
            <strong><?= (int) $summary['percent'] ?>%</strong>
        </div>

        <div class="profile-progress" role="progressbar" aria-valuenow="<?= (int) $summary['percent'] ?>" aria-valuemin="0" aria-valuemax="100">
            <span style="width:<?= (int) $summary['percent'] ?>%"></span>
        </div>

        <?php if (empty($summary['courses'])): ?>
            <p class="text-muted">Ainda não há cursos publicados para calcular o progresso.</p>
        <?php endif; ?>

        <?php foreach ($summary['courses'] as $course): ?>
            <div class="profile-course">
                <div class="profile-course-heading">
                    <div>
                        <h3><?= e((string) $course['title']) ?></h3>
                        <span><?= (int) $course['lessons_completed'] ?> de <?= (int) $course['lessons_count'] ?> aulas</span>
                    </div>
                    <strong><?= (int) $course['percent'] ?>%</strong>
                </div>

                <div class="profile-progress profile-progress-course">
                    <span style="width:<?= (int) $course['percent'] ?>%"></span>
                </div>

                <div class="profile-modules">
                    <?php foreach ($course['modules'] as $module): ?>
                        <div class="profile-module-row">
                            <div class="profile-module-title">
                                <span class="profile-module-number"><?= (int) $module['module_number'] ?></span>
                                <div>
                                    <strong><?= e((string) $module['title']) ?></strong>
                                    <small>
                                        <?= (int) $module['lessons_completed'] ?>/<?= (int) $module['lessons_count'] ?> aulas
                                        <?= !empty($module['completed']) ? ' · prova concluída' : '' ?>
                                    </small>
                                </div>
                            </div>
                            <div class="profile-module-progress">
                                <div class="profile-progress">
                                    <span style="width:<?= (int) $module['percent'] ?>%"></span>
                                </div>
                                <strong><?= (int) $module['percent'] ?>%</strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="profile-two-columns">
        <div class="profile-section panel">
            <span class="page-kicker">Account</span>
            <h2>Dados da conta</h2>

            <dl class="profile-details">
                <div><dt>Nome</dt><dd><?= e($name) ?></dd></div>
                <div><dt>E-mail</dt><dd><?= e($email) ?></dd></div>
                <div><dt>Perfil</dt><dd><?= ($profile['role'] ?? '') === 'admin' ? 'Administrador' : 'Aluno' ?></dd></div>
                <div><dt>Cadastro</dt><dd><?= e($memberSince) ?></dd></div>
            </dl>
        </div>

        <div class="profile-section panel">
            <span class="page-kicker">Access</span>
            <h2>Métodos de acesso</h2>

            <div class="access-methods">
                <div class="access-method <?= !empty($profile['has_google']) ? 'enabled' : '' ?>">
                    <span class="access-method-icon">G</span>
                    <div>
                        <strong>Conta Google</strong>
                        <small><?= !empty($profile['has_google']) ? 'Conectada a esta conta' : 'Não conectada' ?></small>
                    </div>
                    <span><?= !empty($profile['has_google']) ? '✓' : '—' ?></span>
                </div>

                <div class="access-method <?= !empty($profile['has_password']) ? 'enabled' : '' ?>">
                    <span class="access-method-icon">🔑</span>
                    <div>
                        <strong>E-mail e senha</strong>
                        <small><?= !empty($profile['has_password']) ? 'Senha local configurada' : 'Conta sem senha local' ?></small>
                    </div>
                    <span><?= !empty($profile['has_password']) ? '✓' : '—' ?></span>
                </div>
            </div>

            <?php if (!empty($profile['has_google']) && empty($profile['has_password'])): ?>
                <p class="profile-help">
                    Você entrou pelo Google. Se um dia quiser também acessar com e-mail e senha,
                    a recuperação de senha pode criar uma senha local sem remover o acesso Google.
                </p>
            <?php endif; ?>
        </div>
    </section>
</div>
