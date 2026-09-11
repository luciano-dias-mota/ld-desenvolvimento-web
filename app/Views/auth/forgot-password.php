<div class="auth-box auth-box-wide">
    <span class="eyebrow">Account Recovery</span>
    <h1>Recuperar senha</h1>
    <p class="auth-subtitle">
        Informe o e-mail da sua conta. Se ele estiver cadastrado, enviaremos um link seguro para criar uma nova senha.
    </p>

    <form action="<?= url('/esqueci-senha') ?>" method="POST">
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">E-mail da conta</label>
            <input type="email" id="email" name="email" placeholder="voce@exemplo.com" autocomplete="email" maxlength="190" required autofocus>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Enviar link de recuperação</button>
    </form>

    <div class="security-note">
        <strong>🔐 Segurança</strong>
        <p>Por privacidade, a plataforma não informa se um endereço específico possui cadastro.</p>
    </div>

    <p class="text-muted auth-link-line"><a href="<?= url('/login') ?>">← Voltar para o login</a></p>
</div>
