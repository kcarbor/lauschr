<?php $view->startSection('content'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Neue Episode</h1>
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

        <form method="POST" action="<?= $view->url('/feeds/' . $feed['id'] . '/episodes/create') ?>" enctype="multipart/form-data">
            <?= $csrf->field() ?>

            <div class="form-group">
                <label for="audio_file" class="form-label form-label-required">Audio-Datei</label>
                <input
                    type="file"
                    id="audio_file"
                    name="audio_file"
                    class="form-input"
                    accept=".mp3,.m4a,.mp4,.aac,audio/mpeg,audio/mp4,audio/x-m4a,audio/aac"
                    required
                    data-info="audio-info"
                >
                <p class="form-hint">
                    Erlaubte Formate: MP3, M4A, MP4, AAC • Max. 200 MB
                </p>
                <div id="audio-info" style="margin-top: 0.5rem;"></div>
            </div>

            <div class="form-group">
                <label for="title" class="form-label form-label-required">Titel</label>
                <input
                    type="text"
                    id="title"
                    name="title"
                    class="form-input"
                    value="<?= $view->e($old['title'] ?? '') ?>"
                    required
                    placeholder="Episodentitel"
                >
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Beschreibung</label>
                <textarea
                    id="description"
                    name="description"
                    class="form-textarea"
                    rows="4"
                    placeholder="Worum geht es in dieser Episode?"
                ><?= $view->e($old['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="author" class="form-label">Autor</label>
                <input
                    type="text"
                    id="author"
                    name="author"
                    class="form-input"
                    value="<?= $view->e($old['author'] ?? $feed['author'] ?? '') ?>"
                    placeholder="Name des Sprechers"
                >
                <input type="hidden" id="duration" name="duration" value="<?= $view->e($old['duration'] ?? '0') ?>">
            </div>

            <div class="form-group">
                <label for="publish_date" class="form-label">Veröffentlichungsdatum</label>
                <input
                    type="datetime-local"
                    id="publish_date"
                    name="publish_date"
                    class="form-input"
                    value="<?= $view->e($old['publish_date'] ?? date('Y-m-d\TH:i')) ?>"
                >
            </div>

            <div class="form-actions">
                <a href="<?= $view->url('/feeds/' . $feed['id']) ?>" class="btn btn-secondary">Abbrechen</a>
                <button type="submit" class="btn btn-primary">Episode veröffentlichen</button>
            </div>
        </form>
    </div>
</div>

<?php $view->endSection(); ?>
