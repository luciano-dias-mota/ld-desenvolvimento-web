<div class="auth-box">
    <h1>Registrar</h1>
    <form action="<?= url('/register') ?>" method="POST">
        <?= csrf_field() ?>
        <div class="field">
            <label for="name">Nome</label>
            <input type="text" id="name" name="name" required>
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="field">
            <label for="password">Senha</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">Criar conta</button>
    </form>
    <p class="text-muted">Já tem conta? <a href="<?= url('/login') ?>">Faça login</a></p>
</div>