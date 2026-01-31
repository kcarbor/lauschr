<?php
/**
 * CSRF Protection
 *
 * Provides CSRF token generation and validation to protect against
 * Cross-Site Request Forgery attacks.
 */

declare(strict_types=1);

namespace LauschR\Security;

use LauschR\Auth\Session;
use LauschR\Core\App;

class Csrf
{
    private Session $session;
    private string $tokenName;
    private int $tokenLifetime;

    public function __construct(Session $session)
    {
        $this->session = $session;

        $app = App::getInstance();
        $this->tokenName = $app->config('security.csrf_token_name', 'csrf_token');
        $this->tokenLifetime = $app->config('security.csrf_token_lifetime', 3600);
    }

    /**
     * Generate a new CSRF token
     */
    public function generate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set($this->tokenName, [
            'value' => $token,
            'expires' => time() + $this->tokenLifetime,
        ]);

        return $token;
    }

    /**
     * Get the current CSRF token (generates one if not exists)
     */
    public function getToken(): string
    {
        $tokenData = $this->session->get($this->tokenName);

        // Generate new token if none exists or expired
        if (!$tokenData || !isset($tokenData['value']) || $this->isExpired($tokenData)) {
            return $this->generate();
        }

        return $tokenData['value'];
    }

    /**
     * Validate a CSRF token
     */
    public function validate(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $tokenData = $this->session->get($this->tokenName);

        if (!$tokenData || !isset($tokenData['value'])) {
            return false;
        }

        if ($this->isExpired($tokenData)) {
            $this->session->remove($this->tokenName);
            return false;
        }

        // Use timing-safe comparison
        return hash_equals($tokenData['value'], $token);
    }

    /**
     * Validate and consume the token (single-use)
     */
    public function validateOnce(?string $token): bool
    {
        $isValid = $this->validate($token);

        if ($isValid) {
            // Regenerate token after successful validation for single-use
            $this->generate();
        }

        return $isValid;
    }

    /**
     * Check if token is expired
     */
    private function isExpired(array $tokenData): bool
    {
        return !isset($tokenData['expires']) || $tokenData['expires'] < time();
    }

    /**
     * Get the token form field name
     */
    public function getFieldName(): string
    {
        return $this->tokenName;
    }

    /**
     * Generate a hidden form field with the CSRF token
     */
    public function field(): string
    {
        $token = htmlspecialchars($this->getToken(), ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($this->tokenName, ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="' . $name . '" value="' . $token . '">';
    }

    /**
     * Get the token for use in JavaScript/AJAX requests
     */
    public function getTokenForAjax(): array
    {
        return [
            'name' => $this->tokenName,
            'value' => $this->getToken(),
        ];
    }

    /**
     * Validate the token from the current request
     */
    public function validateRequest(): bool
    {
        $token = $_POST[$this->tokenName] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return $this->validate($token);
    }
}
