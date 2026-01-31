<?php $view->startSection('content'); ?>

<div class="page-header">
    <h1 class="page-title">Dashboard</h1>
    <div class="page-actions">
        <a href="<?= $view->url('/feeds/create') ?>" class="btn btn-primary">
            + Neuer Feed
        </a>
    </div>
</div>

<?php if (empty($feeds)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">🎙️</div>
            <h2 class="empty-state-title">Willkommen bei LauschR!</h2>
            <p class="empty-state-description">
                Sie haben noch keine Podcast-Feeds erstellt.<br>
                Erstellen Sie Ihren ersten Feed, um loszulegen.
            </p>
            <a href="<?= $view->url('/feeds/create') ?>" class="btn btn-primary btn-lg">
                Ersten Feed erstellen
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="feed-grid">
        <?php foreach ($feeds as $feed): ?>
            <div class="card feed-card">
                <div class="feed-card-header">
                    <?php if (!empty($feed['image'])): ?>
                        <img src="<?= $view->e($feed['image']) ?>" alt="" class="feed-image">
                    <?php else: ?>
                        <div class="feed-image-placeholder">🎙️</div>
                    <?php endif; ?>

                    <div class="feed-info">
                        <h3 class="feed-title">
                            <a href="<?= $view->url('/feeds/' . $feed['id']) ?>">
                                <?= $view->e($feed['title']) ?>
                            </a>
                        </h3>
                        <div class="feed-meta">
                            <?php
                            $permission = new \LauschR\Models\Permission();
                            $role = $permission->getUserRole($feed, $currentUser['id']);
                            ?>
                            <span class="badge badge-<?= $role === 'owner' ? 'primary' : ($role === 'editor' ? 'success' : 'warning') ?>">
                                <?= $view->e(\LauschR\Models\Permission::getRoleName($role)) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="feed-card-body">
                    <?php if (!empty($feed['description'])): ?>
                        <p class="feed-description">
                            <?= $view->e($feed['description']) ?>
                        </p>
                    <?php endif; ?>

                    <div class="feed-stats">
                        <span class="feed-stat">
                            <strong><?= count($feed['episodes'] ?? []) ?></strong> Episoden
                        </span>
                        <span class="feed-stat">
                            <strong><?= count($feed['collaborators'] ?? []) ?></strong> Mitarbeiter
                        </span>
                    </div>
                </div>

                <div class="card-footer feed-card-footer">
                    <a href="<?= $view->url('/feeds/' . $feed['id']) ?>" class="btn btn-secondary btn-sm">
                        Öffnen
                    </a>
                    <?php if ($permission->canUpload($feed, $currentUser['id'])): ?>
                        <a href="<?= $view->url('/feeds/' . $feed['id'] . '/episodes/create') ?>" class="btn btn-primary btn-sm">
                            + Episode
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php $view->endSection(); ?>
