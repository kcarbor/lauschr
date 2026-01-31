<?php $view->startSection('content'); ?>

<div class="auth-container">
    <div class="auth-header">
        <h1 class="auth-title">Willkommen zurück</h1>
        <p class="auth-subtitle">Melden Sie sich an, um Ihre Podcasts zu verwalten</p>
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

            <form method="POST" action="<?= $view->url('/login') ?>">
                <?= $csrf->field() ?>

                <div class="form-group">
                    <label for="email" class="form-label form-label-required">E-Mail-Adresse</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                        value="<?= $view->e($old['email'] ?? '') ?>"
                        required
                        autofocus
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
                    >
                </div>

                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="remember" value="1">
                        <span>Angemeldet bleiben</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        Anmelden
                    </button>
                </div>
            </form>
        </div>
    </div>

    <p class="auth-footer">
        Noch kein Konto?
        <a href="<?= $view->url('/register') ?>">Jetzt registrieren</a>
    </p>
</div>

<?php $view->endSection(); ?>
