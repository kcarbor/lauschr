<?php
/**
 * View/Template Engine
 *
 * Simple but powerful template rendering with layouts and partials.
 */

declare(strict_types=1);

namespace LauschR\Core;

class View
{
    private string $templatePath;
    private ?string $layout = 'layout';
    private array $data = [];
    private array $sections = [];
    private ?string $currentSection = null;

    public function __construct()
    {
        $this->templatePath = App::getInstance()->config('paths.templates');
    }

    /**
     * Render a template
     */
    public function render(string $template, array $data = []): string
    {
        $this->data = array_merge($this->data, $data);

        // Render the main template (this populates sections via startSection/endSection)
        $content = $this->renderTemplate($template, $this->data);

        // If no layout, return content directly
        if ($this->layout === null) {
            return $content;
        }

        // If template didn't use sections, use the raw output as content
        if (!isset($this->sections['content']) || $this->sections['content'] === '') {
            $this->sections['content'] = $content;
        }

        // Render the layout with the content
        return $this->renderTemplate($this->layout, array_merge($this->data, ['sections' => $this->sections]));
    }

    /**
     * Render a template file
     */
    private function renderTemplate(string $template, array $data): string
    {
        $file = $this->templatePath . '/' . str_replace('.', '/', $template) . '.php';

        if (!file_exists($file)) {
            throw new \RuntimeException("Template not found: {$template}");
        }

        // Extract data to local scope
        extract($data);

        // Make view helper available in templates
        $view = $this;

        ob_start();

        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return ob_get_clean();
    }

    /**
     * Set the layout to use
     */
    public function setLayout(?string $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    /**
     * Disable layout
     */
    public function withoutLayout(): self
    {
        $this->layout = null;
        return $this;
    }

    /**
     * Set global data
     */
    public function share(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Start a section
     */
    public function startSection(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }

    /**
     * End the current section
     */
    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new \RuntimeException('No section started');
        }

        $this->sections[$this->currentSection] = ob_get_clean();
        $this->currentSection = null;
    }

    /**
     * Get a section's content
     */
    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Yield a section (alias for section)
     */
    public function yield(string $name, string $default = ''): string
    {
        return $this->section($name, $default);
    }

    /**
     * Render a partial template
     */
    public function partial(string $template, array $data = []): string
    {
        return $this->renderTemplate('partials/' . $template, array_merge($this->data, $data));
    }

    /**
     * Include a partial and echo it
     */
    public function include(string $template, array $data = []): void
    {
        echo $this->partial($template, $data);
    }

    /**
     * Escape HTML
     */
    public function escape(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Shorthand for escape
     */
    public function e(mixed $value): string
    {
        return $this->escape($value);
    }

    /**
     * Format a date
     */
    public function formatDate(string $date, string $format = 'd.m.Y'): string
    {
        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : '';
    }

    /**
     * Format a datetime
     */
    public function formatDateTime(string $date, string $format = 'd.m.Y H:i'): string
    {
        return $this->formatDate($date, $format);
    }

    /**
     * Check if a value is set and not empty
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]) && $this->data[$key] !== '' && $this->data[$key] !== null;
    }

    /**
     * Get a value with default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Generate a URL
     */
    public function url(string $path = ''): string
    {
        $baseUrl = App::getInstance()->config('app.url');
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Generate an asset URL
     */
    public function asset(string $path): string
    {
        return $this->url('assets/' . ltrim($path, '/'));
    }

    /**
     * Check if the current user can perform an action
     */
    public function can(string $action, array $feed): bool
    {
        $userId = $this->get('currentUserId');

        if (!$userId) {
            return false;
        }

        $permission = new \LauschR\Models\Permission();
        return $permission->can($action, $feed, $userId);
    }
}
