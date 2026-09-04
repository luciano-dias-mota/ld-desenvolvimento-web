<div class="panel" style="max-width:600px; margin:40px auto; text-align:center;">
    <?php if ($certificate): ?>
        <span class="badge-check" style="font-size:1.1rem;">✓ Certificado válido</span>
        <h2 style="margin-top:14px;">Código <?= e($certificate['code']) ?></h2>
        <p>Este certificado foi emitido pela CodeQuest Platform.</p>
    <?php else: ?>
        <h2>Certificado não encontrado</h2>
        <p>Confira se o código foi digitado corretamente.</p>
    <?php endif; ?>
</div>