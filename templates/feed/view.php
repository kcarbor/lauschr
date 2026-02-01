<?php
$view->startSection('content');
$permission = new \LauschR\Models\Permission();
$userPermissions = $permission->getPermissions($feed, $currentUser['id']);
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= $view->e($feed['title']) ?></h1>
        <p class="text-muted">
            <span class="badge badge-<?= $userPermissions['role'] === 'owner' ? 'primary' : ($userPermissions['role'] === 'editor' ? 'success' : 'warning') ?>">
                <?= $view->e(\LauschR\Models\Permission::getRoleName($userPermissions['role'])) ?>
            </span>
            •
            <?= count($feed['episodes'] ?? []) ?> Episoden
        </p>
    </div>
    <div class="page-actions">
        <?php if ($userPermissions['can_upload']): ?>
            <a href="<?= $view->url('/feeds/' . $feed['id'] . '/episodes/create') ?>" class="btn btn-primary">
                + Neue Episode
            </a>
        <?php endif; ?>
        <?php if ($userPermissions['can_manage_settings']): ?>
            <a href="<?= $view->url('/feeds/' . $feed['id'] . '/settings') ?>" class="btn btn-secondary">
                Einstellungen
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Feed Info Card -->
<div class="card mb-6">
    <div class="card-body">
        <div class="d-flex gap-4">
            <?php if (!empty($feed['image'])): ?>
                <img src="<?= $view->url(ltrim($feed['image'], '/')) ?>" alt="" class="feed-image" style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px;">
            <?php else: ?>
                <div class="feed-image-placeholder" style="width: 120px; height: 120px; font-size: 3rem;">🎙️</div>
            <?php endif; ?>

            <div style="flex: 1;">
                <?php if (!empty($feed['description'])): ?>
                    <p><?= $view->e($feed['description']) ?></p>
                <?php endif; ?>

                <div class="d-flex gap-4 mt-4">
                    <div>
                        <strong>RSS Feed:</strong><br>
                        <a href="<?= $view->url('/feed/' . $feed['slug'] . '/rss.xml') ?>" target="_blank" class="text-small">
                            <?= $view->url('/feed/' . $feed['slug'] . '/rss.xml') ?>
                        </a>
                        <button class="btn btn-outline btn-sm" data-copy="<?= $view->url('/feed/' . $feed['slug'] . '/rss.xml') ?>" style="margin-left: 0.5rem;">
                            Kopieren
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="tabs">
    <a href="<?= $view->url('/feeds/' . $feed['id']) ?>" class="tab active">Episoden</a>
    <?php if ($userPermissions['can_invite']): ?>
        <a href="<?= $view->url('/feeds/' . $feed['id'] . '/collaborators') ?>" class="tab">Mitarbeiter</a>
    <?php endif; ?>
</div>

<!-- Episodes List -->
<?php if (empty($feed['episodes'])): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">📻</div>
            <h2 class="empty-state-title">Noch keine Episoden</h2>
            <p class="empty-state-description">
                Fügen Sie Ihre erste Episode hinzu, um Ihren Podcast zu starten.
            </p>
            <?php if ($userPermissions['can_upload']): ?>
                <a href="<?= $view->url('/feeds/' . $feed['id'] . '/episodes/create') ?>" class="btn btn-primary">
                    Erste Episode erstellen
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="episode-list">
            <?php foreach ($feed['episodes'] as $index => $episode): ?>
                <div class="episode-card <?= $index > 0 ? 'border-top' : '' ?>" style="<?= $index > 0 ? 'border-top: 1px solid var(--color-gray-200);' : '' ?>">
                    <div class="episode-number">
                        <?= count($feed['episodes']) - $index ?>
                    </div>

                    <div class="episode-content">
                        <div class="episode-title">
                            <?= $view->e($episode['title']) ?>
                        </div>
                        <div class="episode-meta">
                            <span><?= $view->formatDate($episode['publish_date'] ?? $episode['created_at']) ?></span>
                            <?php if (!empty($episode['duration'])): ?>
                                <span><?= \LauschR\Models\Episode::formatDuration($episode['duration']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($episode['author'])): ?>
                                <span><?= $view->e($episode['author']) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($episode['audio_url'])): ?>
                            <audio controls style="margin-top: 0.5rem; width: 100%; max-width: 400px;">
                                <source src="<?= $view->e($episode['audio_url']) ?>" type="<?= $view->e($episode['mime_type'] ?? 'audio/mpeg') ?>">
                            </audio>
                        <?php endif; ?>
                    </div>

                    <div class="episode-actions">
                        <?php if ($userPermissions['can_edit']): ?>
                            <a href="<?= $view->url('/feeds/' . $feed['id'] . '/episodes/' . $episode['id'] . '/edit') ?>" class="btn btn-outline btn-sm">
                                Bearbeiten
                            </a>
                        <?php endif; ?>
                        <?php if ($userPermissions['can_delete']): ?>
                            <form method="POST" action="<?= $view->url('/feeds/' . $feed['id'] . '/episodes/' . $episode['id'] . '/delete') ?>" style="display: inline;">
                                <?= $csrf->field() ?>
                                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Möchten Sie diese Episode wirklich löschen?">
                                    Löschen
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php $view->endSection(); ?>
