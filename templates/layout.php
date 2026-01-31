<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $view->e($title ?? 'LauschR') ?> - LauschR</title>
    <link rel="stylesheet" href="<?= $view->asset('css/style.css') ?>">
    <?= $view->yield('head') ?>
</head>
<body>
    <header class="header">
        <div class="container">
            <a href="<?= $view->url('/') ?>" class="logo">
                <span class="logo-icon">🎙️</span>
                <span class="logo-text">LauschR</span>
            </a>

            <nav class="nav">
                <?php if (isset($currentUser)): ?>
                    <a href="<?= $view->url('/dashboard') ?>" class="nav-link">Dashboard</a>
                    <a href="<?= $view->url('/feeds/create') ?>" class="nav-link">Neuer Feed</a>
                    <div class="nav-user">
                        <span class="nav-user-name"><?= $view->e($currentUser['name']) ?></span>
                        <a href="<?= $view->url('/logout') ?>" class="nav-link nav-link-secondary">Abmelden</a>
                    </div>
                <?php else: ?>
                    <a href="<?= $view->url('/login') ?>" class="nav-link">Anmelden</a>
                    <a href="<?= $view->url('/register') ?>" class="btn btn-primary btn-sm">Registrieren</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <?php if ($flash = $view->get('flash')): ?>
                <?php foreach ($flash as $type => $message): ?>
                    <div class="alert alert-<?= $view->e($type) ?>">
                        <?= $view->e($message) ?>
                        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?= $view->yield('content') ?>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> LauschR - Podcast Feed Management</p>
        </div>
    </footer>

    <script src="<?= $view->asset('js/app.js') ?>"></script>
    <?= $view->yield('scripts') ?>
</body>
</html>
