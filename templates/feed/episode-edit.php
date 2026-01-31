<?php $view->startSection('content'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Episode bearbeiten</h1>
        <p class="text-muted"><?= $view->e($feed['title']) ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= $view->url('/feeds/' . $feed['id']) ?>" class="btn btn-secondary">
            ← Zurück
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

        <!-- Current Audio -->
        <?php if (!empty($episode['audio_url'])): ?>
            <div class="form-group">
                <label class="form-label">Aktuelle Audio-Datei</label>
                <audio controls style="width: 100%;">
                    <source src="<?= $view->e($episode['audio_url']) ?>" type="<?= $view->e($episode['mime_type'] ?? 'audio/mpeg') ?>">
                </audio>
                <p class="form-hint">
                    <?= $view->e($episode['audio_file'] ?? 'Audio-Datei') ?>
                    <?php if (!empty($episode['file_size'])): ?>
                        • <?= round($episode['file_size'] / 1024 / 1024, 2) ?> MB
                    <?php endif; ?>
                </p>
            </div>
            <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--color-gray-200);">
        <?php endif; ?>

        <form method="POST" action="<?= $view->url('/feeds/' . $feed['id'] . '/episodes/' . $episode['id'] . '/edit') ?>">
            <?= $csrf->field() ?>

            <div class="form-group">
                <label for="title" class="form-label form-label-required">Titel</label>
                <input
                    type="text"
                    id="title"
                    name="title"
                    class="form-input"
                    value="<?= $view->e($old['title'] ?? $episode['title']) ?>"
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
                ><?= $view->e($old['description'] ?? $episode['description'] ?? '') ?></textarea>
            </div>

            <div class="d-flex gap-4">
                <div class="form-group" style="flex: 1;">
                    <label for="author" class="form-label">Autor</label>
                    <input
                        type="text"
                        id="author"
                        name="author"
                        class="form-input"
                        value="<?= $view->e($old['author'] ?? $episode['author'] ?? '') ?>"
                    >
                </div>

                <div class="form-group" style="flex: 1;">
                    <label for="duration" class="form-label">Dauer (Sekunden)</label>
                    <input
                        type="number"
                        id="duration"
                        name="duration"
                        class="form-input"
                        value="<?= $view->e($old['duration'] ?? $episode['duration'] ?? 0) ?>"
                        min="0"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="publish_date" class="form-label">Veröffentlichungsdatum</label>
                <input
                    type="datetime-local"
                    id="publish_date"
                    name="publish_date"
                    class="form-input"
                    value="<?= $view->e(date('Y-m-d\TH:i', strtotime($old['publish_date'] ?? $episode['publish_date'] ?? $episode['created_at']))) ?>"
                >
            </div>

            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" name="explicit" value="1" <?= ($old['explicit'] ?? $episode['explicit'] ?? false) ? 'checked' : '' ?>>
                    <span>Diese Episode enthält explizite Inhalte</span>
                </label>
            </div>

            <div class="form-actions">
                <a href="<?= $view->url('/feeds/' . $feed['id']) ?>" class="btn btn-secondary">Abbrechen</a>
                <button type="submit" class="btn btn-primary">Änderungen speichern</button>
            </div>
        </form>
    </div>
</div>

<?php $view->endSection(); ?>
