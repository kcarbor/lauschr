<?php
/**
 * Application Core
 *
 * Main application class that bootstraps and coordinates all components.
 */

declare(strict_types=1);

namespace LauschR\Core;

class App
{
    private static ?App $instance = null;
    private array $config;
    private array $services = [];

    private function __construct()
    {
        $this->loadConfig();
        $this->setupErrorHandling();
        $this->setupTimezone();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): App
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the application
     */
    public static function boot(string $rootPath): App
    {
        if (!defined('LAUSCHR_ROOT')) {
            define('LAUSCHR_ROOT', $rootPath);
        }
        return self::getInstance();
    }

    /**
     * Load configuration
     */
    private function loadConfig(): void
    {
        $configPath = LAUSCHR_ROOT . '/config/config.php';
        if (!file_exists($configPath)) {
            throw new \RuntimeException('Configuration file not found');
        }
        $this->config = require $configPath;
    }

    /**
     * Setup error handling based on debug mode
     */
    private function setupErrorHandling(): void
    {
        if ($this->config['app']['debug']) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }

        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
    }

    /**
     * Setup timezone
     */
    private function setupTimezone(): void
    {
        date_default_timezone_set($this->config['app']['timezone']);
    }

    /**
     * Get configuration value
     */
    public function config(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Register a service
     */
    public function register(string $name, object $service): void
    {
        $this->services[$name] = $service;
    }

    /**
     * Get a registered service
     */
    public function service(string $name): ?object
    {
        return $this->services[$name] ?? null;
    }

    /**
     * Handle exceptions
     */
    public function handleException(\Throwable $e): void
    {
        $this->logError($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        if ($this->config['app']['debug']) {
            echo "<h1>Error</h1>";
            echo "<p><strong>" . htmlspecialchars($e->getMessage()) . "</strong></p>";
            echo "<p>File: " . htmlspecialchars($e->getFile()) . " on line " . $e->getLine() . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        } else {
            http_response_code(500);
            echo "An error occurred. Please try again later.";
        }
        exit(1);
    }

    /**
     * Handle errors
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * Log an error
     */
    public function logError(string $message, array $context = []): void
    {
        $logFile = LAUSCHR_ROOT . '/data/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] {$message}{$contextStr}\n";

        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log an info message
     */
    public function logInfo(string $message, array $context = []): void
    {
        $logFile = LAUSCHR_ROOT . '/data/app.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] INFO: {$message}{$contextStr}\n";

        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}
