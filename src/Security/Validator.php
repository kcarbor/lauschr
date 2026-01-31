<?php
/**
 * Input Validation
 *
 * Provides input validation and sanitization for user-submitted data.
 */

declare(strict_types=1);

namespace LauschR\Security;

class Validator
{
    private array $errors = [];
    private array $data = [];
    private array $validated = [];

    /**
     * Create a new validator instance
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Static factory method
     */
    public static function make(array $data): self
    {
        return new self($data);
    }

    /**
     * Validate required field
     */
    public function required(string $field, ?string $message = null): self
    {
        $value = $this->getValue($field);

        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, $message ?? "Das Feld '{$field}' ist erforderlich.");
        }

        return $this;
    }

    /**
     * Validate email format
     */
    public function email(string $field, ?string $message = null): self
    {
        $value = $this->getValue($field);

        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, $message ?? "Bitte geben Sie eine gültige E-Mail-Adresse ein.");
        }

        return $this;
    }

    /**
     * Validate minimum length
     */
    public function minLength(string $field, int $min, ?string $message = null): self
    {
        $value = $this->getValue($field);

        if ($value !== null && strlen($value) < $min) {
            $this->addError($field, $message ?? "Das Feld '{$field}' muss mindestens {$min} Zeichen lang sein.");
        }

        return $this;
    }

    /**
     * Validate maximum length
     */
    public function maxLength(string $field, int $max, ?string $message = null): self
    {
        $value = $this->getValue($field);

        if ($value !== null && strlen($value) > $max) {
            $this->addError($field, $message ?? "Das Feld '{$field}' darf maximal {$max} Zeichen lang sein.");
        }

        return $this;
    }

    /**
     * Validate numeric value
     */
    public function numeric(string $field, ?string $message = null): self
    {
        $value = $this->getValue($field);

        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->addError($field, $message ?? "Das Feld '{$field}' muss eine Zahl sein.");
        }

        return $this;
    }

    /**
     * Validate integer value
     */
    public function integer(string $field, ?string $message = null): self
    {
        $value = $this->getValue($field);

        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, $message ?? "Das Feld '{$field}' muss eine ganze Zahl sein.");
        }

        return $this;
    }

    /**
     * Validate minimum value
     */
    public function min(string $field, float $min, ?string $message = null): self
    {
        $value = $this->getValue($field);

        if ($value !== null && is_numeric($value) && (float)$value < $min) {
            $this->addError($field, $message ?? "Das Feld '{$field}' muss mindestens {$min} sein.");
        }

        return $this;
    }

    /**
     * Validate maximum value
     */
    public function max(string $field, float $max, ?string $message = null): self
    {
        $value = $this->getValue($field);

        if ($value !== null && is_numeric($value) && (float)$value > $max) {
            $this->addError($field, $message ?? "Das Feld '{$field}' darf maximal {$max} sein.");
        }

        return $this;
    }

    /**
     * Validate value is in a list of allowed values
     */
    public function in(string $field, array $allowed, ?string $message = null): self
    {
        $value = $this->getValue($field);

        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
            $this->addError($field, $message ?? "Das Feld '{$field}' enthält keinen gültigen Wert.");
        }

        return $this;
    }

    /**
     * Validate against regex pattern
     */
    public function regex(string $field, string $pattern, ?string $message = null): self
    {
        $value = $this->getValue($field);

        if ($value !== null && $value !== '' && !preg_match($pattern, $value)) {
            $this->addError($field, $message ?? "Das Feld '{$field}' hat ein ungültiges Format.");
        }

        return $this;
    }

    /**
     * Validate URL format
     */
    public function url(string $field, ?string $message = null): self
    {
        $value = $this->getValue($field);

        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, $message ?? "Bitte geben Sie eine gültige URL ein.");
        }

        return $this;
    }

    /**
     * Validate date format
     */
    public function date(string $field, string $format = 'Y-m-d', ?string $message = null): self
    {
        $value = $this->getValue($field);

        if ($value !== null && $value !== '') {
            $date = \DateTime::createFromFormat($format, $value);
            if (!$date || $date->format($format) !== $value) {
                $this->addError($field, $message ?? "Das Feld '{$field}' muss ein gültiges Datum sein.");
            }
        }

        return $this;
    }

    /**
     * Validate datetime format
     */
    public function datetime(string $field, string $format = 'Y-m-d\TH:i', ?string $message = null): self
    {
        return $this->date($field, $format, $message);
    }

    /**
     * Validate that two fields match
     */
    public function matches(string $field, string $otherField, ?string $message = null): self
    {
        $value = $this->getValue($field);
        $otherValue = $this->getValue($otherField);

        if ($value !== $otherValue) {
            $this->addError($field, $message ?? "Die Felder '{$field}' und '{$otherField}' müssen übereinstimmen.");
        }

        return $this;
    }

    /**
     * Validate slug format (lowercase, alphanumeric, hyphens)
     */
    public function slug(string $field, ?string $message = null): self
    {
        return $this->regex($field, '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $message ?? "Das Feld '{$field}' muss ein gültiger Slug sein.");
    }

    /**
     * Custom validation with callback
     */
    public function custom(string $field, callable $callback, string $message): self
    {
        $value = $this->getValue($field);

        if (!$callback($value, $this->data)) {
            $this->addError($field, $message);
        }

        return $this;
    }

    /**
     * Mark a field as validated and get its sanitized value
     */
    public function sanitize(string $field, string $type = 'string'): self
    {
        $value = $this->getValue($field);

        $sanitized = match ($type) {
            'string' => $this->sanitizeString($value),
            'email' => filter_var($value, FILTER_SANITIZE_EMAIL),
            'int', 'integer' => (int)$value,
            'float' => (float)$value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'url' => filter_var($value, FILTER_SANITIZE_URL),
            'html' => htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'),
            default => $value,
        };

        $this->validated[$field] = $sanitized;

        return $this;
    }

    /**
     * Sanitize a string value
     */
    private function sanitizeString(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        // Remove null bytes and trim
        $value = str_replace("\0", '', $value);
        $value = trim($value);

        return $value;
    }

    /**
     * Get a value from the data array (supports dot notation)
     */
    private function getValue(string $field): mixed
    {
        $keys = explode('.', $field);
        $value = $this->data;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Add an error
     */
    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Check if validation passed
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Check if validation failed
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Get all errors
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field
     */
    public function errorsFor(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get first error for a field
     */
    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get all first errors (one per field)
     */
    public function firstErrors(): array
    {
        $first = [];
        foreach ($this->errors as $field => $messages) {
            $first[$field] = $messages[0] ?? null;
        }
        return $first;
    }

    /**
     * Get all validated and sanitized data
     */
    public function validated(): array
    {
        return $this->validated;
    }

    /**
     * Get a validated value
     */
    public function get(string $field, mixed $default = null): mixed
    {
        return $this->validated[$field] ?? $default;
    }

    /**
     * Static helper to generate a slug from a string
     */
    public static function slugify(string $text): string
    {
        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Replace German umlauts
        $text = str_replace(
            ['ä', 'ö', 'ü', 'ß'],
            ['ae', 'oe', 'ue', 'ss'],
            $text
        );

        // Replace non-alphanumeric characters with hyphens
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        // Remove leading/trailing hyphens
        $text = trim($text, '-');

        // Remove multiple consecutive hyphens
        $text = preg_replace('/-+/', '-', $text);

        return $text;
    }
}
