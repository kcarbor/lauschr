<?php
/**
 * Simple Router
 *
 * Handles URL routing for the application.
 */

declare(strict_types=1);

namespace LauschR\Core;

class Router
{
    private array $routes = [];
    private array $middleware = [];
    private string $basePath = '';
    private static string $staticBasePath = '';

    /**
     * Set the base path for all routes
     */
    public function setBasePath(string $basePath): void
    {
        $this->basePath = rtrim($basePath, '/');
        self::$staticBasePath = $this->basePath;
    }

    /**
     * Add a GET route
     */
    public function get(string $pattern, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $pattern, $handler, $middleware);
    }

    /**
     * Add a POST route
     */
    public function post(string $pattern, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $pattern, $handler, $middleware);
    }

    /**
     * Add a route for any method
     */
    public function any(string $pattern, callable|array $handler, array $middleware = []): self
    {
        $this->addRoute('GET', $pattern, $handler, $middleware);
        $this->addRoute('POST', $pattern, $handler, $middleware);
        return $this;
    }

    /**
     * Add a route
     */
    private function addRoute(string $method, string $pattern, callable|array $handler, array $middleware): self
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $this->basePath . $pattern,
            'handler' => $handler,
            'middleware' => array_merge($this->middleware, $middleware),
        ];

        return $this;
    }

    /**
     * Add global middleware
     */
    public function addMiddleware(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Dispatch the current request
     */
    public function dispatch(): mixed
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Remove trailing slash except for root (both / and /basepath/)
        if ($uri !== '/' && $uri !== $this->basePath . '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchRoute($route['pattern'], $uri);

            if ($params !== false) {
                // Run middleware
                foreach ($route['middleware'] as $middleware) {
                    $result = $middleware();
                    if ($result === false) {
                        return null;
                    }
                }

                // Execute handler
                return $this->executeHandler($route['handler'], $params);
            }
        }

        // No route matched
        http_response_code(404);
        return $this->render404();
    }

    /**
     * Match a route pattern against a URI
     *
     * @return array|false Parameters array if matched, false otherwise
     */
    private function matchRoute(string $pattern, string $uri): array|false
    {
        // Convert pattern to regex
        $regex = preg_replace_callback('/\{([a-zA-Z_]+)\}/', function ($matches) {
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $pattern);

        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Filter out numeric keys
            return array_filter($matches, fn($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
        }

        return false;
    }

    /**
     * Execute a route handler
     */
    private function executeHandler(callable|array $handler, array $params): mixed
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;

            if (is_string($class)) {
                $class = new $class();
            }

            return $class->$method(...array_values($params));
        }

        return $handler(...array_values($params));
    }

    /**
     * Render 404 page
     */
    private function render404(): string
    {
        $view = new View();
        return $view->render('errors/404', [
            'title' => 'Seite nicht gefunden',
        ]);
    }

    /**
     * Redirect to a URL
     */
    public static function redirect(string $url, int $statusCode = 302): void
    {
        // Prepend base path for relative URLs
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $url = self::$staticBasePath . $url;
        }
        header('Location: ' . $url, true, $statusCode);
        exit;
    }

    /**
     * Redirect back to the previous page
     */
    public static function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        self::redirect($referer);
    }

    /**
     * Get the current URL
     */
    public static function currentUrl(): string
    {
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];

        return "{$scheme}://{$host}{$uri}";
    }

    /**
     * Get query parameter
     */
    public static function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Get POST data
     */
    public static function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Get all POST data
     */
    public static function allInput(): array
    {
        return $_POST;
    }

    /**
     * Check if request is AJAX
     */
    public static function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Return JSON response
     */
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
