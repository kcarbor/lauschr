<?php $view->startSection('content'); ?>

<div class="empty-state">
    <div class="empty-state-icon">🔍</div>
    <h1 class="empty-state-title">Seite nicht gefunden</h1>
    <p class="empty-state-description">
        Die angeforderte Seite existiert nicht oder wurde verschoben.
    </p>
    <a href="<?= $view->url('/') ?>" class="btn btn-primary">
        Zur Startseite
    </a>
</div>

<?php $view->endSection(); ?>
