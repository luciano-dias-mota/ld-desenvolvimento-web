<div class="auth-box auth-box-wide">
    <span class="eyebrow">New Player</span>
    <h1>Criar sua conta</h1>
    <p class="auth-subtitle">Salve seu progresso, acumule XP e desbloqueie o certificado.</p>

    <?php if (!empty($googleEnabled)): ?>
        <div class="google-access">
            <div id="google-signin-button" data-google-client-id="<?= e((string) $googleClientId) ?>"></div>
        </div>
        <div class="auth-divider"><span>ou</span></div>
    <?php endif; ?>

    <form action="<?= url('/register') ?>" method="POST">
        <?= csrf_field() ?>
        <div class="field"><label for="name">Nome</label><input type="text" id="name" name="name" placeholder="Seu nome" autocomplete="name" maxlength="100" required></div>
        <div class="field"><label for="email">E-mail</label><input type="email" id="email" name="email" placeholder="voce@exemplo.com" autocomplete="email" maxlength="190" required></div>
        <div class="field"><label for="password">Senha</label><input type="password" id="password" name="password" placeholder="Mínimo de 8 caracteres" autocomplete="new-password" minlength="8" maxlength="128" required></div>
        <div class="field"><label for="password_confirmation">Confirmar senha</label><input type="password" id="password_confirmation" name="password_confirmation" placeholder="Repita sua senha" autocomplete="new-password" minlength="8" maxlength="128" required></div>
        <button type="submit" class="btn btn-primary btn-block">Criar conta e salvar progresso</button>
    </form>

    <p class="text-muted auth-link-line">Já possui conta? <a href="<?= url('/login') ?>">Fazer login</a></p>

    <div class="guest-access-card">
        <div><strong>👾 Quer conhecer primeiro?</strong><p>Explore aulas, exercícios e provas. Nada será gravado e o certificado fica disponível somente para contas.</p></div>
        <form action="<?= url('/visitante/entrar') ?>" method="POST">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline btn-block">Explorar como visitante</button>
        </form>
    </div>

    <?php if (!empty($googleEnabled)): ?>
        <form id="google-login-form" action="<?= url('/auth/google') ?>" method="POST" class="visually-hidden" aria-hidden="true">
            <?= csrf_field() ?>
            <input type="hidden" name="credential" id="google-credential">
        </form>
    <?php endif; ?>
</div>
