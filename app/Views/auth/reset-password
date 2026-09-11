<div class="auth-box auth-box-wide">
    <span class="eyebrow">New Password</span>
    <h1>Criar nova senha</h1>
    <p class="auth-subtitle">Use entre 8 e 128 caracteres e confirme a nova senha abaixo.</p>

    <form action="<?= url('/redefinir-senha/' . rawurlencode((string) $resetToken)) ?>" method="POST">
        <?= csrf_field() ?>

        <div class="field">
            <label for="password">Nova senha</label>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="Mínimo de 8 caracteres"
                autocomplete="new-password"
                minlength="8"
                maxlength="128"
                required
                autofocus
            >
        </div>

        <div class="field">
            <label for="password_confirmation">Confirmar nova senha</label>
            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                placeholder="Repita a nova senha"
                autocomplete="new-password"
                minlength="8"
                maxlength="128"
                required
            >
        </div>

        <button type="submit" class="btn btn-primary btn-block">Salvar nova senha</button>
    </form>

    <p class="text-muted auth-link-line"><a href="<?= url('/login') ?>">← Voltar para o login</a></p>
</div>
