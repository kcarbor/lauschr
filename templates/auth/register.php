<?php $view->startSection('content'); ?>

<div class="auth-container">
    <div class="auth-header">
        <h1 class="auth-title">Konto erstellen</h1>
        <p class="auth-subtitle">Starten Sie Ihren eigenen Podcast-Feed</p>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <div><?= $view->e($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= $view->url('/register') ?>">
                <?= $csrf->field() ?>

                <div class="form-group">
                    <label for="name" class="form-label form-label-required">Name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-input <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                        value="<?= $view->e($old['name'] ?? '') ?>"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="email" class="form-label form-label-required">E-Mail-Adresse</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                        value="<?= $view->e($old['email'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label form-label-required">Passwort</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        required
                        minlength="8"
                    >
                    <p class="form-hint">
                        Mindestens 8 Zeichen mit Groß-/Kleinbuchstaben und Zahlen
                    </p>
                </div>

                <div class="form-group">
                    <label for="password_confirmation" class="form-label form-label-required">Passwort bestätigen</label>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        class="form-input"
                        required
                    >
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        Registrieren
                    </button>
                </div>
            </form>
        </div>
    </div>

    <p class="auth-footer">
        Bereits registriert?
        <a href="<?= $view->url('/login') ?>">Jetzt anmelden</a>
    </p>
</div>

<?php $view->endSection(); ?>
