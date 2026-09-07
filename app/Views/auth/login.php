<div class="auth-box auth-box-wide">
    <span class="eyebrow">Player Login</span>
    <h1>Entrar na jornada</h1>
    <p class="auth-subtitle">Acesse seu progresso e continue de onde parou.</p>

    <?php if (!empty($googleEnabled)): ?>
        <div class="google-access"><div id="google-signin-button" data-google-client-id="<?= e((string) $googleClientId) ?>"></div></div>
        <div class="auth-divider"><span>ou</span></div>
    <?php endif; ?>

    <form action="<?= url('/login') ?>" method="POST">
        <?= csrf_field() ?>
        <div class="field"><label for="email">E-mail</label><input type="email" id="email" name="email" placeholder="voce@exemplo.com" autocomplete="email" required></div>
        <div class="field"><label for="password">Senha</label><input type="password" id="password" name="password" placeholder="Digite sua senha" autocomplete="current-password" required></div>
        <button type="submit" class="btn btn-primary btn-block">Entrar</button>
    </form>

    <p class="text-muted auth-link-line">Ainda não tem conta? <a href="<?= url('/register') ?>">Criar cadastro</a></p>

    <div class="guest-access-card">
        <div><strong>👾 Entrar sem conta</strong><p>O conteúdo fica disponível para exploração, mas seu avanço não será salvo.</p></div>
        <form action="<?= url('/visitante/entrar') ?>" method="POST">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline btn-block">Continuar como visitante</button>
        </form>
    </div>

    <?php if (!empty($googleEnabled)): ?>
        <form id="google-login-form" action="<?= url('/auth/google') ?>" method="POST" class="visually-hidden" aria-hidden="true">
            <?= csrf_field() ?><input type="hidden" name="credential" id="google-credential">
        </form>
    <?php endif; ?>
</div>
