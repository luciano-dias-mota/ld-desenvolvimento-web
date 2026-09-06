<div class="auth-box">
    <span class="eyebrow">New Player</span>
    <h1>Criar sua conta</h1>
    <p class="auth-subtitle">Prepare seu perfil e comece a primeira fase.</p>

    <form action="<?= url('/register') ?>" method="POST">
        <?= csrf_field() ?>

        <div class="field">
            <label for="name">Nome</label>
            <input type="text" id="name" name="name" placeholder="Seu nome" autocomplete="name" required>
        </div>

        <div class="field">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" placeholder="voce@exemplo.com" autocomplete="email" required>
        </div>

        <div class="field">
            <label for="password">Senha</label>
            <input type="password" id="password" name="password" placeholder="Crie uma senha segura" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-primary">Criar conta</button>
    </form>

    <p class="text-muted">Já possui conta? <a href="<?= url('/login') ?>">Fazer login</a></p>
</div>
