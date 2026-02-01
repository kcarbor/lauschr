<?php $view->startSection('content'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Feed-Einstellungen</h1>
        <p class="text-muted"><?= $view->e($feed['title']) ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= $view->url('/feeds/' . $feed['id']) ?>" class="btn btn-secondary">
            ← Zurück zum Feed
        </a>
    </div>
</div>

<div class="card" style="max-width: 700px;">
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <div><?= $view->e($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= $view->url('/feeds/' . $feed['id'] . '/settings') ?>" enctype="multipart/form-data">
            <?= $csrf->field() ?>

            <div class="form-group">
                <label class="form-label">Feed-Cover</label>
                <div class="d-flex gap-4" style="align-items: flex-start;">
                    <div class="feed-cover-preview" style="flex-shrink: 0;">
                        <?php if (!empty($feed['image'])): ?>
                            <img src="<?= $view->url(ltrim($feed['image'], '/')) ?>" alt="Feed Cover" style="width: 150px; height: 150px; object-fit: cover; border-radius: 8px; border: 1px solid var(--color-gray-200);">
                        <?php else: ?>
                            <div style="width: 150px; height: 150px; background: var(--color-gray-100); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 3rem; border: 1px solid var(--color-gray-200);">
                                🎙️
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1;">
                        <input
                            type="file"
                            id="cover_image"
                            name="cover_image"
                            class="form-input"
                            accept="image/jpeg,image/png,image/webp"
                        >
                        <p class="form-hint">
                            Empfohlen: Quadratisches Bild, min. 1400x1400 Pixel.<br>
                            Erlaubte Formate: JPG, PNG, WebP. Max. 5 MB.
                        </p>
                        <?php if (!empty($feed['image'])): ?>
                            <label class="form-checkbox" style="margin-top: 0.5rem;">
                                <input type="checkbox" name="remove_image" value="1">
                                <span>Aktuelles Bild entfernen</span>
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="title" class="form-label form-label-required">Titel</label>
                <input
                    type="text"
                    id="title"
                    name="title"
                    class="form-input"
                    value="<?= $view->e($feed['title']) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Beschreibung</label>
                <textarea
                    id="description"
                    name="description"
                    class="form-textarea"
                    rows="4"
                ><?= $view->e($feed['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="author" class="form-label">Autor / Host</label>
                <input
                    type="text"
                    id="author"
                    name="author"
                    class="form-input"
                    value="<?= $view->e($feed['author'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="email" class="form-label">E-Mail (für iTunes)</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-input"
                    value="<?= $view->e($feed['email'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="category" class="form-label">Kategorie</label>
                <select id="category" name="category" class="form-select">
                    <option value="">Kategorie wählen...</option>
                    <?php foreach (\LauschR\Feed\RssGenerator::getCategories() as $cat => $subcats): ?>
                        <option value="<?= $view->e($cat) ?>" <?= ($feed['category'] ?? '') === $cat ? 'selected' : '' ?>>
                            <?= $view->e($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="language" class="form-label">Sprache</label>
                <select id="language" name="language" class="form-select">
                    <option value="de" <?= ($feed['language'] ?? 'de') === 'de' ? 'selected' : '' ?>>Deutsch</option>
                    <option value="en" <?= ($feed['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                    <option value="fr" <?= ($feed['language'] ?? '') === 'fr' ? 'selected' : '' ?>>Français</option>
                    <option value="es" <?= ($feed['language'] ?? '') === 'es' ? 'selected' : '' ?>>Español</option>
                </select>
            </div>

            <div class="form-group">
                <label for="website" class="form-label">Website</label>
                <input
                    type="url"
                    id="website"
                    name="website"
                    class="form-input"
                    value="<?= $view->e($feed['website'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" name="explicit" value="1" <?= ($feed['explicit'] ?? false) ? 'checked' : '' ?>>
                    <span>Enthält explizite Inhalte</span>
                </label>
            </div>

            <hr style="margin: 2rem 0; border: none; border-top: 1px solid var(--color-gray-200);">

            <h3 style="margin-bottom: 1rem;">Feed-URLs</h3>

            <div class="form-group">
                <label class="form-label">RSS Feed URL</label>
                <div class="d-flex gap-2">
                    <input type="text" class="form-input" value="<?= $view->url('/feed/' . $feed['slug'] . '/rss.xml') ?>" readonly>
                    <button type="button" class="btn btn-outline" data-copy="<?= $view->url('/feed/' . $feed['slug'] . '/rss.xml') ?>">
                        Kopieren
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Öffentliche Seite</label>
                <div class="d-flex gap-2">
                    <input type="text" class="form-input" value="<?= $view->url('/feed/' . $feed['slug']) ?>" readonly>
                    <button type="button" class="btn btn-outline" data-copy="<?= $view->url('/feed/' . $feed['slug']) ?>">
                        Kopieren
                    </button>
                </div>
            </div>

            <div class="form-actions">
                <a href="<?= $view->url('/feeds/' . $feed['id']) ?>" class="btn btn-secondary">Abbrechen</a>
                <button type="submit" class="btn btn-primary">Änderungen speichern</button>
            </div>
        </form>
    </div>
</div>

<?php if ($permission->canDeleteFeed($feed, $currentUser['id'])): ?>
    <div class="card mt-6" style="max-width: 700px; border: 1px solid var(--color-danger);">
        <div class="card-header" style="background: #fef2f2;">
            <h3 class="card-title" style="color: var(--color-danger);">Gefahrenzone</h3>
        </div>
        <div class="card-body">
            <p>Das Löschen des Feeds ist unwiderruflich. Alle Episoden und Audiodateien werden dauerhaft gelöscht.</p>
            <form method="POST" action="<?= $view->url('/feeds/' . $feed['id'] . '/delete') ?>" style="margin-top: 1rem;">
                <?= $csrf->field() ?>
                <button type="submit" class="btn btn-danger" data-confirm="Sind Sie sicher, dass Sie diesen Feed und alle Episoden unwiderruflich löschen möchten?">
                    Feed dauerhaft löschen
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php $view->endSection(); ?>
