<div class="auth-box">
    <span class="eyebrow">Player Login</span>
    <h1>Entrar na jornada</h1>
    <p class="auth-subtitle">Acesse seu progresso e continue de onde parou.</p>

    <form action="<?= url('/login') ?>" method="POST">
        <?= csrf_field() ?>

        <div class="field">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" placeholder="voce@exemplo.com" autocomplete="email" required>
        </div>

        <div class="field">
            <label for="password">Senha</label>
            <input type="password" id="password" name="password" placeholder="Digite sua senha" autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn btn-primary">Entrar</button>
    </form>

    <p class="text-muted">Ainda não tem conta? <a href="<?= url('/register') ?>">Criar cadastro</a></p>
</div>
