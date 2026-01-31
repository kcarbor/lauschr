<?php
/**
 * Session Management
 *
 * Handles secure session management for user authentication.
 */

declare(strict_types=1);

namespace LauschR\Auth;

use LauschR\Core\App;

class Session
{
    private bool $started = false;
    private array $config;

    public function __construct()
    {
        $app = App::getInstance();
        $this->config = $app->config('session', []);
    }

    /**
     * Start the session with secure settings
     */
    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        // Configure session settings
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_set_cookie_params([
            'lifetime' => $this->config['lifetime'] ?? 86400,
            'path' => '/',
            'domain' => '',
            'secure' => $this->config['secure'] ?? false,
            'httponly' => $this->config['httponly'] ?? true,
            'samesite' => $this->config['samesite'] ?? 'Strict',
        ]);

        session_name($this->config['name'] ?? 'lauschr_session');
        session_start();

        $this->started = true;

        // Regenerate session ID periodically for security
        $this->regenerateIfNeeded();
    }

    /**
     * Regenerate session ID if needed
     */
    private function regenerateIfNeeded(): void
    {
        $lastRegeneration = $_SESSION['_last_regeneration'] ?? 0;
        $regenerationInterval = 1800; // 30 minutes

        if (time() - $lastRegeneration > $regenerationInterval) {
            $this->regenerate();
        }
    }

    /**
     * Regenerate session ID
     */
    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['_last_regeneration'] = time();
        }
    }

    /**
     * Set a session value
     */
    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if a session key exists
     */
    public function has(string $key): bool
    {
        $this->ensureStarted();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session value
     */
    public function remove(string $key): void
    {
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    /**
     * Get all session data
     */
    public function all(): array
    {
        $this->ensureStarted();
        return $_SESSION;
    }

    /**
     * Clear all session data
     */
    public function clear(): void
    {
        $this->ensureStarted();
        $_SESSION = [];
    }

    /**
     * Destroy the session completely
     */
    public function destroy(): void
    {
        $this->ensureStarted();

        // Clear session data
        $_SESSION = [];

        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $this->config['secure'] ?? false,
                    'httponly' => $this->config['httponly'] ?? true,
                    'samesite' => $this->config['samesite'] ?? 'Strict',
                ]
            );
        }

        // Destroy the session
        session_destroy();
        $this->started = false;
    }

    /**
     * Set flash message (available for next request only)
     */
    public function flash(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Get and remove flash message
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /**
     * Check if flash message exists
     */
    public function hasFlash(string $key): bool
    {
        $this->ensureStarted();
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Get all flash messages and clear them
     */
    public function getAllFlash(): array
    {
        $this->ensureStarted();
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }

    /**
     * Ensure session is started
     */
    private function ensureStarted(): void
    {
        if (!$this->started) {
            $this->start();
        }
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->has('user_id') && $this->get('user_id') !== null;
    }

    /**
     * Get authenticated user ID
     */
    public function getUserId(): ?string
    {
        return $this->get('user_id');
    }

    /**
     * Set authenticated user
     */
    public function setUser(string $userId): void
    {
        $this->regenerate(); // Prevent session fixation
        $this->set('user_id', $userId);
        $this->set('login_time', time());
    }

    /**
     * Log out the current user
     */
    public function logout(): void
    {
        $this->remove('user_id');
        $this->remove('login_time');
        $this->regenerate();
    }
}
