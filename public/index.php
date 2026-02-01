<?php
/**
 * LauschR - Main Entry Point
 *
 * All requests are routed through this file.
 */

declare(strict_types=1);

// Define root path
// Use __DIR__ when index.php is in the same folder as src/, config/, etc. (production)
// Use dirname(__DIR__) when index.php is in a public/ subfolder (development)
// Auto-detect: if src/ exists in current dir, use __DIR__, otherwise use parent
define('LAUSCHR_ROOT', is_dir(__DIR__ . '/src') ? __DIR__ : dirname(__DIR__));

// Composer autoload (if using composer) or manual autoloading
spl_autoload_register(function ($class) {
    // Convert namespace to path
    $prefix = 'LauschR\\';
    $baseDir = LAUSCHR_ROOT . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use LauschR\Core\App;
use LauschR\Core\Router;
use LauschR\Core\View;
use LauschR\Auth\Session;
use LauschR\Auth\Password;
use LauschR\Security\Csrf;
use LauschR\Security\Validator;
use LauschR\Storage\JsonStore;
use LauschR\Models\User;
use LauschR\Models\Feed;
use LauschR\Models\Episode;
use LauschR\Models\Permission;
use LauschR\Feed\RssGenerator;

// Bootstrap application
$app = App::boot(LAUSCHR_ROOT);

// Initialize core services
$session = new Session();
$session->start();

$store = new JsonStore($app->config('paths.data'));
$password = new Password();
$csrf = new Csrf($session);
$userModel = new User($store, $password);
$feedModel = new Feed($store, new Permission());
$episodeModel = new Episode($store);
$permission = new Permission();

// Create view instance with shared data
$view = new View();
$view->share('csrf', $csrf);

// Get current user if logged in
$currentUser = null;
if ($session->isAuthenticated()) {
    $currentUser = $userModel->find($session->getUserId());
    $view->share('currentUser', $currentUser);
    $view->share('currentUserId', $currentUser['id'] ?? null);
}

// Flash messages
$view->share('flash', $session->getAllFlash());

// Create router
$router = new Router();

// Set base path for subdirectory installation (e.g., /raime-upload)
// Auto-detect from script path
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = rtrim(dirname($scriptName), '/');
if ($basePath !== '' && $basePath !== '/') {
    $router->setBasePath($basePath);
}

// Middleware: Require authentication
$requireAuth = function () use ($session, $router) {
    if (!$session->isAuthenticated()) {
        $session->flash('error', 'Bitte melden Sie sich an.');
        Router::redirect('/login');
        return false;
    }
    return true;
};

// Middleware: Require guest (not logged in)
$requireGuest = function () use ($session) {
    if ($session->isAuthenticated()) {
        Router::redirect('/dashboard');
        return false;
    }
    return true;
};

// =============================================================================
// Routes
// =============================================================================

// Home page
$router->get('/', function () use ($view, $session) {
    if ($session->isAuthenticated()) {
        Router::redirect('/dashboard');
        return;
    }

    return $view->render('auth/login', ['title' => 'Anmelden']);
});

// Login
$router->get('/login', function () use ($view, $csrf) {
    return $view->render('auth/login', [
        'title' => 'Anmelden',
        'old' => [],
        'errors' => [],
    ]);
}, [$requireGuest]);

$router->post('/login', function () use ($view, $csrf, $session, $userModel) {
    // Validate CSRF
    if (!$csrf->validateRequest()) {
        return $view->render('auth/login', [
            'title' => 'Anmelden',
            'errors' => ['Ungültige Anfrage. Bitte versuchen Sie es erneut.'],
            'old' => Router::allInput(),
        ]);
    }

    $email = Router::input('email', '');
    $password = Router::input('password', '');

    // Validate input
    $validator = Validator::make(['email' => $email, 'password' => $password]);
    $validator->required('email')->email('email');
    $validator->required('password');

    if ($validator->fails()) {
        return $view->render('auth/login', [
            'title' => 'Anmelden',
            'errors' => array_merge(...array_values($validator->errors())),
            'old' => ['email' => $email],
        ]);
    }

    // Attempt authentication
    $result = $userModel->authenticate($email, $password);

    if (is_string($result)) {
        $errorMessages = [
            'invalid_credentials' => 'E-Mail oder Passwort ist falsch.',
            'pending' => 'Ihr Konto wartet noch auf Freigabe durch einen Administrator.',
            'rejected' => 'Ihr Konto wurde abgelehnt. Bitte kontaktieren Sie den Administrator.',
        ];
        return $view->render('auth/login', [
            'title' => 'Anmelden',
            'errors' => [$errorMessages[$result] ?? 'Anmeldung fehlgeschlagen.'],
            'old' => ['email' => $email],
        ]);
    }

    // Log in
    $session->setUser($result['id']);
    $session->flash('success', 'Willkommen zurück, ' . $result['name'] . '!');

    Router::redirect('/dashboard');
}, [$requireGuest]);

// Register
$router->get('/register', function () use ($view) {
    return $view->render('auth/register', [
        'title' => 'Registrieren',
        'old' => [],
        'errors' => [],
    ]);
}, [$requireGuest]);

$router->post('/register', function () use ($view, $csrf, $session, $userModel, $password) {
    if (!$csrf->validateRequest()) {
        return $view->render('auth/register', [
            'title' => 'Registrieren',
            'errors' => ['Ungültige Anfrage. Bitte versuchen Sie es erneut.'],
            'old' => Router::allInput(),
        ]);
    }

    $name = Router::input('name', '');
    $email = Router::input('email', '');
    $pw = Router::input('password', '');
    $pwConfirm = Router::input('password_confirmation', '');

    // Validate
    $validator = Validator::make([
        'name' => $name,
        'email' => $email,
        'password' => $pw,
        'password_confirmation' => $pwConfirm,
    ]);

    $validator->required('name')->minLength('name', 2)->maxLength('name', 100);
    $validator->required('email')->email('email');
    $validator->required('password')->minLength('password', 8);
    $validator->required('password_confirmation')->matches('password_confirmation', 'password', 'Die Passwörter stimmen nicht überein.');

    // Check if email exists
    if ($userModel->emailExists($email)) {
        return $view->render('auth/register', [
            'title' => 'Registrieren',
            'errors' => ['Diese E-Mail-Adresse wird bereits verwendet.'],
            'old' => ['name' => $name, 'email' => $email],
        ]);
    }

    // Validate password strength
    $pwErrors = $password->validate($pw);
    if (!empty($pwErrors)) {
        return $view->render('auth/register', [
            'title' => 'Registrieren',
            'errors' => $pwErrors,
            'old' => ['name' => $name, 'email' => $email],
        ]);
    }

    if ($validator->fails()) {
        return $view->render('auth/register', [
            'title' => 'Registrieren',
            'errors' => array_merge(...array_values($validator->errors())),
            'old' => ['name' => $name, 'email' => $email],
        ]);
    }

    // Create user
    $user = $userModel->create([
        'name' => $name,
        'email' => $email,
        'password' => $pw,
    ]);

    // Check if user is auto-approved (first user = admin)
    if (($user['status'] ?? 'pending') === 'approved') {
        $session->setUser($user['id']);
        $session->flash('success', 'Willkommen bei LauschR, ' . $user['name'] . '!');
        Router::redirect('/dashboard');
    }

    // User needs approval
    Router::redirect('/pending');
}, [$requireGuest]);

// Pending approval page
$router->get('/pending', function () use ($view) {
    return $view->render('auth/pending', [
        'title' => 'Registrierung eingegangen',
    ]);
});

// Logout
$router->get('/logout', function () use ($session) {
    $session->logout();
    $session->flash('success', 'Sie wurden erfolgreich abgemeldet.');
    Router::redirect('/login');
});

// =============================================================================
// ADMIN ROUTES
// =============================================================================

// Middleware: Require admin or moderator
$requireModerator = function () use ($session, $userModel) {
    if (!$session->isAuthenticated()) {
        Router::redirect('/login');
        return false;
    }
    $userId = $session->getUserId();
    if (!$userModel->isModerator($userId)) {
        Router::redirect('/dashboard');
        return false;
    }
    return true;
};

// Middleware: Require admin only
$requireAdmin = function () use ($session, $userModel) {
    if (!$session->isAuthenticated()) {
        Router::redirect('/login');
        return false;
    }
    $userId = $session->getUserId();
    if (!$userModel->isAdmin($userId)) {
        Router::redirect('/dashboard');
        return false;
    }
    return true;
};

// Admin: User management
$router->get('/admin/users', function () use ($view, $userModel) {
    $pending = $userModel->getByStatus('pending');
    $approved = $userModel->getByStatus('approved');
    $rejected = $userModel->getByStatus('rejected');

    return $view->render('admin/users', [
        'title' => 'Benutzerverwaltung',
        'pending' => $pending,
        'approved' => $approved,
        'rejected' => $rejected,
    ]);
}, [$requireModerator]);

// Admin: Approve user
$router->post('/admin/users/{id}/approve', function ($id) use ($userModel, $session, $csrf) {
    if (!$csrf->validateRequest()) {
        $session->flash('error', 'Ungültige Anfrage.');
        Router::redirect('/admin/users');
        return;
    }

    if ($userModel->approve($id)) {
        $user = $userModel->find($id);
        $session->flash('success', 'Benutzer "' . ($user['name'] ?? $id) . '" wurde freigeschaltet.');
    } else {
        $session->flash('error', 'Benutzer konnte nicht freigeschaltet werden.');
    }

    Router::redirect('/admin/users');
}, [$requireModerator]);

// Admin: Reject user
$router->post('/admin/users/{id}/reject', function ($id) use ($userModel, $session, $csrf) {
    if (!$csrf->validateRequest()) {
        $session->flash('error', 'Ungültige Anfrage.');
        Router::redirect('/admin/users');
        return;
    }

    if ($userModel->reject($id)) {
        $user = $userModel->find($id);
        $session->flash('success', 'Benutzer "' . ($user['name'] ?? $id) . '" wurde abgelehnt.');
    } else {
        $session->flash('error', 'Benutzer konnte nicht abgelehnt werden.');
    }

    Router::redirect('/admin/users');
}, [$requireModerator]);

// Admin: Set user role (admin only)
$router->post('/admin/users/{id}/role', function ($id) use ($userModel, $session, $csrf) {
    if (!$csrf->validateRequest()) {
        $session->flash('error', 'Ungültige Anfrage.');
        Router::redirect('/admin/users');
        return;
    }

    $role = Router::input('role', 'user');
    if ($userModel->setRole($id, $role)) {
        $user = $userModel->find($id);
        $roleNames = ['admin' => 'Administrator', 'moderator' => 'Moderator', 'user' => 'Benutzer'];
        $session->flash('success', 'Rolle von "' . ($user['name'] ?? $id) . '" wurde zu "' . ($roleNames[$role] ?? $role) . '" geändert.');
    } else {
        $session->flash('error', 'Rolle konnte nicht geändert werden.');
    }

    Router::redirect('/admin/users');
}, [$requireAdmin]);

// Admin: Delete user (admin only)
$router->post('/admin/users/{id}/delete', function ($id) use ($userModel, $session, $csrf) {
    if (!$csrf->validateRequest()) {
        $session->flash('error', 'Ungültige Anfrage.');
        Router::redirect('/admin/users');
        return;
    }

    // Prevent self-deletion
    if ($id === $session->getUserId()) {
        $session->flash('error', 'Sie können sich nicht selbst löschen.');
        Router::redirect('/admin/users');
        return;
    }

    $user = $userModel->find($id);
    if ($userModel->delete($id)) {
        $session->flash('success', 'Benutzer "' . ($user['name'] ?? $id) . '" wurde gelöscht.');
    } else {
        $session->flash('error', 'Benutzer konnte nicht gelöscht werden.');
    }

    Router::redirect('/admin/users');
}, [$requireAdmin]);

// Dashboard
$router->get('/dashboard', function () use ($view, $feedModel, $currentUser) {
    $feeds = $feedModel->getAccessibleByUser($currentUser['id']);

    return $view->render('dashboard', [
        'title' => 'Dashboard',
        'feeds' => $feeds,
    ]);
}, [$requireAuth]);

// Create feed
$router->get('/feeds/create', function () use ($view) {
    return $view->render('feed/create', [
        'title' => 'Neuer Feed',
        'old' => [],
        'errors' => [],
    ]);
}, [$requireAuth]);

$router->post('/feeds/create', function () use ($view, $csrf, $session, $feedModel, $currentUser) {
    if (!$csrf->validateRequest()) {
        return $view->render('feed/create', [
            'title' => 'Neuer Feed',
            'errors' => ['Ungültige Anfrage.'],
            'old' => Router::allInput(),
        ]);
    }

    $validator = Validator::make(Router::allInput());
    $validator->required('title')->minLength('title', 2)->maxLength('title', 200);

    if ($validator->fails()) {
        return $view->render('feed/create', [
            'title' => 'Neuer Feed',
            'errors' => array_merge(...array_values($validator->errors())),
            'old' => Router::allInput(),
        ]);
    }

    $feed = $feedModel->create(Router::allInput(), $currentUser['id']);

    $session->flash('success', 'Feed "' . $feed['title'] . '" wurde erstellt.');
    Router::redirect('/feeds/' . $feed['id']);
}, [$requireAuth]);

// View feed
$router->get('/feeds/{id}', function ($id) use ($view, $feedModel, $permission, $currentUser, $session) {
    $feed = $feedModel->find($id);

    if (!$feed) {
        http_response_code(404);
        return $view->render('errors/404', ['title' => 'Feed nicht gefunden']);
    }

    if (!$permission->hasAccess($feed, $currentUser['id'])) {
        $session->flash('error', 'Sie haben keinen Zugriff auf diesen Feed.');
        Router::redirect('/dashboard');
        return;
    }

    return $view->render('feed/view', [
        'title' => $feed['title'],
        'feed' => $feed,
    ]);
}, [$requireAuth]);

// Feed settings
$router->get('/feeds/{id}/settings', function ($id) use ($view, $feedModel, $permission, $currentUser, $session) {
    $feed = $feedModel->find($id);

    if (!$feed || !$permission->canManageSettings($feed, $currentUser['id'])) {
        $session->flash('error', 'Zugriff verweigert.');
        Router::redirect('/dashboard');
        return;
    }

    return $view->render('feed/settings', [
        'title' => 'Feed-Einstellungen',
        'feed' => $feed,
        'permission' => $permission,
        'errors' => [],
    ]);
}, [$requireAuth]);

$router->post('/feeds/{id}/settings', function ($id) use ($view, $csrf, $session, $feedModel, $permission, $currentUser, $app) {
    $feed = $feedModel->find($id);

    if (!$feed || !$permission->canManageSettings($feed, $currentUser['id'])) {
        $session->flash('error', 'Zugriff verweigert.');
        Router::redirect('/dashboard');
        return;
    }

    if (!$csrf->validateRequest()) {
        $session->flash('error', 'Ungültige Anfrage.');
        Router::redirect('/feeds/' . $id . '/settings');
        return;
    }

    $updateData = Router::allInput();

    // Handle image removal
    if (!empty($updateData['remove_image'])) {
        // Delete old image file if exists
        if (!empty($feed['image'])) {
            $oldImagePath = LAUSCHR_ROOT . parse_url($feed['image'], PHP_URL_PATH);
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
        }
        $updateData['image'] = null;
    }

    // Handle image upload
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['cover_image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5 MB

        // Validate file type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $allowedTypes)) {
            $session->flash('error', 'Ungültiges Bildformat. Erlaubt sind: JPG, PNG, WebP.');
            Router::redirect('/feeds/' . $id . '/settings');
            return;
        }

        // Validate file size
        if ($file['size'] > $maxSize) {
            $session->flash('error', 'Das Bild ist zu groß. Maximale Größe: 5 MB.');
            Router::redirect('/feeds/' . $id . '/settings');
            return;
        }

        // Create images directory if not exists
        $imagesDir = LAUSCHR_ROOT . '/data/images';
        if (!is_dir($imagesDir)) {
            mkdir($imagesDir, 0755, true);
        }

        // Generate unique filename
        $extension = match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg'
        };
        $filename = 'cover_' . $id . '_' . time() . '.' . $extension;
        $targetPath = $imagesDir . '/' . $filename;

        // Delete old image if exists
        if (!empty($feed['image'])) {
            $oldImagePath = LAUSCHR_ROOT . parse_url($feed['image'], PHP_URL_PATH);
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
        }

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Store relative path - will be resolved by View::asset() or url()
            $updateData['image'] = '/data/images/' . $filename;
        } else {
            $session->flash('error', 'Fehler beim Hochladen des Bildes.');
            Router::redirect('/feeds/' . $id . '/settings');
            return;
        }
    }

    $feedModel->update($id, $updateData);
    $session->flash('success', 'Einstellungen wurden gespeichert.');
    Router::redirect('/feeds/' . $id . '/settings');
}, [$requireAuth]);

// Delete feed
$router->post('/feeds/{id}/delete', function ($id) use ($csrf, $session, $feedModel, $permission, $currentUser) {
    $feed = $feedModel->find($id);

    if (!$feed || !$permission->canDeleteFeed($feed, $currentUser['id'])) {
        $session->flash('error', 'Zugriff verweigert.');
        Router::redirect('/dashboard');
        return;
    }

    if (!$csrf->validateRequest()) {
        $session->flash('error', 'Ungültige Anfrage.');
        Router::redirect('/feeds/' . $id . '/settings');
        return;
    }

    $feedModel->delete($id);
    $session->flash('success', 'Feed wurde gelöscht.');
    Router::redirect('/dashboard');
}, [$requireAuth]);

// Collaborators
$router->get('/feeds/{id}/collaborators', function ($id) use ($view, $feedModel, $userModel, $permission, $currentUser, $session) {
    $feed = $feedModel->find($id);

    if (!$feed || !$permission->canInvite($feed, $currentUser['id'])) {
        $session->flash('error', 'Zugriff verweigert.');
        Router::redirect('/dashboard');
        return;
    }

    $owner = $userModel->find($feed['owner_id']);
    $collaborators = [];

    foreach ($feed['collaborators'] ?? [] as $userId => $role) {
        $user = $userModel->find($userId);
        if ($user) {
            $collaborators[] = ['user' => $user, 'role' => $role];
        }
    }

    return $view->render('feed/collaborators', [
        'title' => 'Mitarbeiter',
        'feed' => $feed,
        'owner' => $owner,
        'collaborators' => $collaborators,
        'permission' => $permission,
        'errors' => [],
    ]);
}, [$requireAuth]);

$router->post('/feeds/{id}/collaborators/invite', function ($id) use ($view, $csrf, $session, $feedModel, $userModel, $permission, $currentUser) {
    $feed = $feedModel->find($id);

    if (!$feed || !$permission->canInvite($feed, $currentUser['id'])) {
        $session->flash('error', 'Zugriff verweigert.');
        Router::redirect('/dashboard');
        return;
    }

    if (!$csrf->validateRequest()) {
        $session->flash('error', 'Ungültige Anfrage.');
        Router::redirect('/feeds/' . $id . '/collaborators');
        return;
    }

    $email = Router::input('email', '');
    $role = Router::input('role', 'viewer');

    // Find user by email
    $user = $userModel->findByEmail($email);

    if (!$user) {
        $session->flash('error', 'Benutzer mit dieser E-Mail-Adresse nicht gefunden.');
        Router::redirect('/feeds/' . $id . '/collaborators');
        return;
    }

    if ($user['id'] === $feed['owner_id']) {
        $session->flash('error', 'Der Besitzer kann nicht als Mitarbeiter hinzugefügt werden.');
        Router::redirect('/feeds/' . $id . '/collaborators');
        return;
    }

    if (isset($feed['collaborators'][$user['id']])) {
        $session->flash('error', 'Dieser Benutzer ist bereits Mitarbeiter.');
        Router::redirect('/feeds/' . $id . '/collaborators');
        return;
    }

    $feedModel->addCollaborator($id, $user['id'], $role);
    $session->flash('success', $user['name'] . ' wurde als Mitarbeiter hinzugefügt.');
    Router::redirect('/feeds/' . $id . '/collaborators');
}, [$requireAuth]);

$router->post('/feeds/{id}/collaborators/{userId}/remove', function ($id, $userId) use ($csrf, $session, $feedModel, $permission, $currentUser) {
    $feed = $feedModel->find($id);

    if (!$feed || !$permission->canInvite($feed, $currentUser['id'])) {
        $session->flash('error', 'Zugriff verweigert.');
        Router::redirect('/dashboard');
        return;
    }

    if (!$csrf->validateRequest()) {
        $session->flash('error', 'Ungültige Anfrage.');
        Router::redirect('/feeds/' . $id . '/collaborators');
        return;
    }

    $feedModel->removeCollaborator($id, $userId);
    $session->flash('success', 'Mitarbeiter wurde entfernt.');
    Router::redirect('/feeds/' . $id . '/collaborators');
}, [$requireAuth]);

// Create episode
$router->get('/feeds/{id}/episodes/create', function ($id) use ($view, $feedModel, $permission, $currentUser, $session) {
    $feed = $feedModel->find($id);

    if (!$feed || !$permission->canUpload($feed, $currentUser['id'])) {
        $session->flash('error', 'Zugriff verweigert.');
        Router::redirect('/dashboard');
        return;
    }

    return $view->render('feed/upload', [
        'title' => 'Neue Episode',
        'feed' => $feed,
        'old' => [],
        'errors' => [],
    ]);
}, [$requireAuth]);

$router->post('/feeds/{id}/episodes/create', function ($id) use ($view, $csrf, $session, $feedModel, $episodeModel, $permission, $currentUser) {
    $feed = $feedModel->find($id);

    if (!$feed || !$permission->canUpload($feed, $currentUser['id'])) {
        $session->flash('error', 'Zugriff verweigert.');
        Router::redirect('/dashboard');
        return;
    }

    if (!$csrf->validateRequest()) {
        return $view->render('feed/upload', [
            'title' => 'Neue Episode',
            'feed' => $feed,
            'errors' => ['Ungültige Anfrage.'],
            'old' => Router::allInput(),
        ]);
    }

    // Check for file upload
    if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] === UPLOAD_ERR_NO_FILE) {
        return $view->render('feed/upload', [
            'title' => 'Neue Episode',
            'feed' => $feed,
            'errors' => ['Bitte wählen Sie eine Audio-Datei aus.'],
            'old' => Router::allInput(),
        ]);
    }

    $validator = Validator::make(Router::allInput());
    $validator->required('title')->minLength('title', 2)->maxLength('title', 500);

    if ($validator->fails()) {
        return $view->render('feed/upload', [
            'title' => 'Neue Episode',
            'feed' => $feed,
            'errors' => array_merge(...array_values($validator->errors())),
            'old' => Router::allInput(),
        ]);
    }

    try {
        $episodeData = Router::allInput();
        $episodeData['created_by'] = $currentUser['id'];

        $episode = $episodeModel->create($id, $episodeData, $_FILES['audio_file']);

        $session->flash('success', 'Episode "' . $episode['title'] . '" wurde veröffentlicht.');
        Router::redirect('/feeds/' . $id);
    } catch (\RuntimeException $e) {
        return $view->render('feed/upload', [
            'title' => 'Neue Episode',
            'feed' => $feed,
            'errors' => [$e->getMessage()],
            'old' => Router::allInput(),
        ]);
    }
}, [$requireAuth]);

// Edit episode
$router->get('/feeds/{feedId}/episodes/{episodeId}/edit', function ($feedId, $episodeId) use ($view, $feedModel, $episodeModel, $permission, $currentUser, $session) {
    $feed = $feedModel->find($feedId);
    $episode = $episodeModel->find($feedId, $episodeId);

    if (!$feed || !$episode || !$permission->canEdit($feed, $currentUser['id'])) {
        $session->flash('error', 'Zugriff verweigert.');
        Router::redirect('/dashboard');
        return;
    }

    return $view->render('feed/episode-edit', [
        'title' => 'Episode bearbeiten',
        'feed' => $feed,
        'episode' => $episode,
        'old' => [],
        'errors' => [],
    ]);
}, [$requireAuth]);

$router->post('/feeds/{feedId}/episodes/{episodeId}/edit', function ($feedId, $episodeId) use ($view, $csrf, $session, $feedModel, $episodeModel, $permission, $currentUser) {
    $feed = $feedModel->find($feedId);
    $episode = $episodeModel->find($feedId, $episodeId);

    if (!$feed || !$episode || !$permission->canEdit($feed, $currentUser['id'])) {
        $session->flash('error', 'Zugriff verweigert.');
        Router::redirect('/dashboard');
        return;
    }

    if (!$csrf->validateRequest()) {
        $session->flash('error', 'Ungültige Anfrage.');
        Router::redirect('/feeds/' . $feedId . '/episodes/' . $episodeId . '/edit');
        return;
    }

    $validator = Validator::make(Router::allInput());
    $validator->required('title')->minLength('title', 2)->maxLength('title', 500);

    if ($validator->fails()) {
        return $view->render('feed/episode-edit', [
            'title' => 'Episode bearbeiten',
            'feed' => $feed,
            'episode' => $episode,
            'errors' => array_merge(...array_values($validator->errors())),
            'old' => Router::allInput(),
        ]);
    }

    $episodeModel->update($feedId, $episodeId, Router::allInput());
    $session->flash('success', 'Episode wurde aktualisiert.');
    Router::redirect('/feeds/' . $feedId);
}, [$requireAuth]);

// Delete episode
$router->post('/feeds/{feedId}/episodes/{episodeId}/delete', function ($feedId, $episodeId) use ($csrf, $session, $feedModel, $episodeModel, $permission, $currentUser) {
    $feed = $feedModel->find($feedId);

    if (!$feed || !$permission->canDelete($feed, $currentUser['id'])) {
        $session->flash('error', 'Zugriff verweigert.');
        Router::redirect('/dashboard');
        return;
    }

    if (!$csrf->validateRequest()) {
        $session->flash('error', 'Ungültige Anfrage.');
        Router::redirect('/feeds/' . $feedId);
        return;
    }

    $episodeModel->delete($feedId, $episodeId);
    $session->flash('success', 'Episode wurde gelöscht.');
    Router::redirect('/feeds/' . $feedId);
}, [$requireAuth]);

// Public feed page
$router->get('/feed/{slug}', function ($slug) use ($view, $feedModel) {
    $feed = $feedModel->findBySlug($slug);

    if (!$feed) {
        http_response_code(404);
        return $view->render('errors/404', ['title' => 'Feed nicht gefunden']);
    }

    $view->setLayout(null);
    return $view->render('feed/public', [
        'feed' => $feed,
    ]);
});

// RSS feed
$router->get('/feed/{slug}/rss.xml', function ($slug) use ($feedModel) {
    $feed = $feedModel->findBySlug($slug);

    if (!$feed) {
        http_response_code(404);
        echo 'Feed not found';
        return;
    }

    $generator = new RssGenerator();
    $xml = $generator->generate($feed);

    header('Content-Type: application/rss+xml; charset=UTF-8');
    echo $xml;
});

// Serve audio files
$router->get('/audio/{feedId}/{filename}', function ($feedId, $filename) use ($app) {
    $audioPath = $app->config('paths.audio') . '/' . $feedId . '/' . $filename;

    if (!file_exists($audioPath)) {
        http_response_code(404);
        echo 'File not found';
        return;
    }

    // Determine MIME type
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mimeTypes = [
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
        'mp4' => 'audio/mp4',
        'aac' => 'audio/aac',
    ];

    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

    // Serve file
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($audioPath));
    header('Accept-Ranges: bytes');

    readfile($audioPath);
});

// Dispatch the request
echo $router->dispatch();
