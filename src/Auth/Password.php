<?php
/**
 * Password Handling
 *
 * Secure password hashing and verification using PHP's built-in functions.
 */

declare(strict_types=1);

namespace LauschR\Auth;

use LauschR\Core\App;

class Password
{
    private int $minLength;
    private string|int $algorithm;
    private array $options;

    public function __construct()
    {
        $app = App::getInstance();
        $this->minLength = $app->config('security.password_min_length', 8);

        // Use Argon2id if available, fall back to bcrypt
        if (defined('PASSWORD_ARGON2ID')) {
            $this->algorithm = PASSWORD_ARGON2ID;
            $this->options = [
                'memory_cost' => 65536,  // 64 MB
                'time_cost' => 4,
                'threads' => 3,
            ];
        } else {
            $this->algorithm = PASSWORD_BCRYPT;
            $this->options = ['cost' => 12];
        }
    }

    /**
     * Hash a password
     */
    public function hash(string $password): string
    {
        return password_hash($password, $this->algorithm, $this->options);
    }

    /**
     * Verify a password against a hash
     */
    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if a hash needs to be rehashed (algorithm or options changed)
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algorithm, $this->options);
    }

    /**
     * Validate password strength
     *
     * @return array Array of validation errors, empty if valid
     */
    public function validate(string $password): array
    {
        $errors = [];

        if (strlen($password) < $this->minLength) {
            $errors[] = "Passwort muss mindestens {$this->minLength} Zeichen lang sein.";
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Passwort muss mindestens einen Großbuchstaben enthalten.";
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Passwort muss mindestens einen Kleinbuchstaben enthalten.";
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Passwort muss mindestens eine Zahl enthalten.";
        }

        return $errors;
    }

    /**
     * Check if password is strong enough
     */
    public function isStrong(string $password): bool
    {
        return empty($this->validate($password));
    }

    /**
     * Generate a secure random password
     */
    public function generate(int $length = 16): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }

        return $password;
    }

    /**
     * Generate a secure token (for password reset, etc.)
     */
    public function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}
