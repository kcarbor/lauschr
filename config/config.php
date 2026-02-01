<?php
/**
 * LauschR Configuration
 *
 * Central configuration file for the application.
 * Sensitive values should be set via environment variables or a local config override.
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('LAUSCHR_ROOT')) {
    die('Direct access not permitted');
}

// Auto-detect URL for local development
$defaultUrl = 'https://lauschr.io';
if (PHP_SAPI === 'cli-server' || (isset($_SERVER['HTTP_HOST']) && str_contains($_SERVER['HTTP_HOST'], 'localhost'))) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $defaultUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8000');
}

return [
    // Application settings
    'app' => [
        'name' => 'LauschR',
        'url' => getenv('APP_URL') ?: $defaultUrl,
        'debug' => (bool)(getenv('APP_DEBUG') ?: false),
        'timezone' => 'Europe/Berlin',
        'language' => 'de',
    ],

    // Path configuration
    'paths' => [
        'root' => LAUSCHR_ROOT,
        'data' => LAUSCHR_ROOT . '/data',
        'users' => LAUSCHR_ROOT . '/data/users.json',
        'feeds' => LAUSCHR_ROOT . '/data/feeds',
        'audio' => LAUSCHR_ROOT . '/data/audio',
        'templates' => LAUSCHR_ROOT . '/templates',
        'public' => LAUSCHR_ROOT . '/public',
    ],

    // Session configuration
    'session' => [
        'name' => 'lauschr_session',
        'lifetime' => 86400, // 24 hours
        // Auto-detect: secure cookies for HTTPS, non-secure for localhost development
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ],

    // Security settings
    'security' => [
        'csrf_token_name' => 'csrf_token',
        'csrf_token_lifetime' => 3600, // 1 hour
        'password_min_length' => 8,
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
    ],

    // Upload settings
    'upload' => [
        'max_file_size' => 200 * 1024 * 1024, // 200 MB
        'allowed_types' => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'video/mp4'],
        'allowed_extensions' => ['mp3', 'm4a', 'mp4', 'aac'],
    ],

    // Feed settings
    'feed' => [
        'default_language' => 'de',
        'default_explicit' => false,
        'max_episodes_in_feed' => 100,
    ],

    // Permission levels
    'permissions' => [
        'owner' => [
            'level' => 100,
            'can_upload' => true,
            'can_edit' => true,
            'can_delete' => true,
            'can_invite' => true,
            'can_manage_settings' => true,
            'can_delete_feed' => true,
        ],
        'editor' => [
            'level' => 50,
            'can_upload' => true,
            'can_edit' => true,
            'can_delete' => true,
            'can_invite' => false,
            'can_manage_settings' => false,
            'can_delete_feed' => false,
        ],
        'contributor' => [
            'level' => 30,
            'can_upload' => true,
            'can_edit' => false,
            'can_delete' => false,
            'can_invite' => false,
            'can_manage_settings' => false,
            'can_delete_feed' => false,
        ],
        'viewer' => [
            'level' => 10,
            'can_upload' => false,
            'can_edit' => false,
            'can_delete' => false,
            'can_invite' => false,
            'can_manage_settings' => false,
            'can_delete_feed' => false,
        ],
    ],
];
