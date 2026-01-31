<?php $view->startSection('content'); ?>

<div class="page-header">
    <h1 class="page-title">Neuen Feed erstellen</h1>
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

        <form method="POST" action="<?= $view->url('/feeds/create') ?>" enctype="multipart/form-data">
            <?= $csrf->field() ?>

            <div class="form-group">
                <label for="title" class="form-label form-label-required">Titel</label>
                <input
                    type="text"
                    id="title"
                    name="title"
                    class="form-input"
                    value="<?= $view->e($old['title'] ?? '') ?>"
                    required
                    autofocus
                    placeholder="z.B. Mein Podcast"
                >
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Beschreibung</label>
                <textarea
                    id="description"
                    name="description"
                    class="form-textarea"
                    rows="4"
                    placeholder="Worum geht es in diesem Podcast?"
                ><?= $view->e($old['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="author" class="form-label">Autor / Host</label>
                <input
                    type="text"
                    id="author"
                    name="author"
                    class="form-input"
                    value="<?= $view->e($old['author'] ?? $currentUser['name'] ?? '') ?>"
                    placeholder="Name des Podcast-Hosts"
                >
            </div>

            <div class="form-group">
                <label for="language" class="form-label">Sprache</label>
                <select id="language" name="language" class="form-select">
                    <option value="de" <?= ($old['language'] ?? 'de') === 'de' ? 'selected' : '' ?>>Deutsch</option>
                    <option value="en" <?= ($old['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                    <option value="fr" <?= ($old['language'] ?? '') === 'fr' ? 'selected' : '' ?>>Français</option>
                    <option value="es" <?= ($old['language'] ?? '') === 'es' ? 'selected' : '' ?>>Español</option>
                </select>
            </div>

            <div class="form-actions">
                <a href="<?= $view->url('/dashboard') ?>" class="btn btn-secondary">Abbrechen</a>
                <button type="submit" class="btn btn-primary">Feed erstellen</button>
            </div>
        </form>
    </div>
</div>

<?php $view->endSection(); ?>
