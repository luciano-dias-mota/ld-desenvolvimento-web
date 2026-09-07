<div class="panel center" style="max-width:680px; margin:40px auto;">
    <?php if ($certificate): ?>
        <span class="badge-check">✓ Certificado válido</span>
        <h2 style="margin-top:16px;">LD Desenvolvimento Web</h2>
        <p><strong>Aluno:</strong> <?= e($user['name'] ?? '') ?></p>
        <p><strong>Curso:</strong> <?= e($course['title'] ?? '') ?></p>
        <p><strong>Emitido em:</strong> <?= !empty($certificate['issued_at']) ? e((new DateTime($certificate['issued_at']))->format('d/m/Y')) : '—' ?></p>
        <p class="code"><strong>Código:</strong> <?= e($certificate['certificate_code'] ?? '') ?></p>
        <p class="text-muted" style="margin-inline:auto;">Este certificado foi emitido pela plataforma LD Desenvolvimento Web.</p>
    <?php else: ?>
        <span class="eyebrow">Validation Error</span>
        <h2>Certificado não encontrado</h2>
        <p class="text-muted" style="margin-inline:auto;">Confira se o código informado é válido.</p>
    <?php endif; ?>
</div>
