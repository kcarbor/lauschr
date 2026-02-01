<?php $view->startSection('content'); ?>

<div class="auth-container">
    <div class="auth-header">
        <h1 class="auth-title">Registrierung eingegangen</h1>
        <p class="auth-subtitle">Vielen Dank für Ihre Registrierung!</p>
    </div>

    <div class="card">
        <div class="card-body" style="text-align: center;">
            <div style="font-size: 4rem; margin-bottom: 1rem;">⏳</div>
            <p style="margin-bottom: 1rem;">
                Ihre Registrierung wurde erfolgreich übermittelt und wartet nun auf Freigabe durch einen Administrator.
            </p>
            <p style="color: var(--color-gray-500);">
                Sie erhalten Zugang zu LauschR, sobald Ihr Konto freigeschaltet wurde.
            </p>
        </div>
    </div>

    <p class="auth-footer">
        <a href="<?= $view->url('/login') ?>">Zurück zur Anmeldung</a>
    </p>
</div>

<?php $view->endSection(); ?>
