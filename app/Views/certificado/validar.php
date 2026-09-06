<div class="panel center" style="max-width:600px; margin:40px auto;">
    <?php if ($certificate): ?>
        <span class="badge-check">✓ Certificado válido</span>
        <h2 style="margin-top:16px;">Código <?= e($certificate['code']) ?></h2>
        <p class="text-muted" style="margin-inline:auto;">Este certificado foi emitido pela PHP Quest.</p>
    <?php else: ?>
        <span class="eyebrow">Validation Error</span>
        <h2>Certificado não encontrado</h2>
        <p class="text-muted" style="margin-inline:auto;">Confira se o código foi digitado corretamente.</p>
    <?php endif; ?>
</div>
