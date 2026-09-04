<div class="auth-box">
    <h1>Entrar</h1>
    <form action="<?= url('/login') ?>" method="POST">
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="field">
            <label for="password">Senha</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">Entrar</button>
    </form>
    <p class="text-muted">Não tem conta? <a href="<?= url('/register') ?>">Registre-se</a></p>
</div>