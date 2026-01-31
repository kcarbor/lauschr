<?php
/**
 * JSON Storage Layer
 *
 * Handles reading and writing JSON files with proper file locking
 * to prevent data corruption from concurrent access.
 */

declare(strict_types=1);

namespace LauschR\Storage;

class JsonStore
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->ensureDirectory($this->basePath);
    }

    /**
     * Read data from a JSON file
     *
     * @param string $filename Relative path from base path
     * @param array $default Default value if file doesn't exist
     * @return array
     */
    public function read(string $filename, array $default = []): array
    {
        $path = $this->resolvePath($filename);

        if (!file_exists($path)) {
            return $default;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file for reading: {$path}");
        }

        try {
            // Acquire shared lock for reading
            if (!flock($handle, LOCK_SH)) {
                throw new \RuntimeException("Cannot acquire read lock: {$path}");
            }

            $content = stream_get_contents($handle);
            flock($handle, LOCK_UN);

            if ($content === false || $content === '') {
                return $default;
            }

            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("JSON decode error: " . json_last_error_msg());
            }

            return $data ?? $default;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Write data to a JSON file
     *
     * @param string $filename Relative path from base path
     * @param array $data Data to write
     * @return bool
     */
    public function write(string $filename, array $data): bool
    {
        $path = $this->resolvePath($filename);
        $this->ensureDirectory(dirname($path));

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file for writing: {$path}");
        }

        try {
            // Acquire exclusive lock for writing
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException("Cannot acquire write lock: {$path}");
            }

            // Truncate and write
            ftruncate($handle, 0);
            rewind($handle);

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new \RuntimeException("JSON encode error: " . json_last_error_msg());
            }

            $result = fwrite($handle, $json);
            fflush($handle);
            flock($handle, LOCK_UN);

            return $result !== false;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Atomically update a JSON file using a callback
     *
     * @param string $filename Relative path from base path
     * @param callable $callback Function that receives current data and returns updated data
     * @param array $default Default value if file doesn't exist
     * @return array The updated data
     */
    public function update(string $filename, callable $callback, array $default = []): array
    {
        $path = $this->resolvePath($filename);
        $this->ensureDirectory(dirname($path));

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file for update: {$path}");
        }

        try {
            // Acquire exclusive lock
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException("Cannot acquire lock: {$path}");
            }

            // Read current content
            $content = stream_get_contents($handle);
            $currentData = $default;

            if ($content !== false && $content !== '') {
                $decoded = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                    $currentData = $decoded;
                }
            }

            // Apply the callback
            $newData = $callback($currentData);

            // Write back
            ftruncate($handle, 0);
            rewind($handle);

            $json = json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new \RuntimeException("JSON encode error: " . json_last_error_msg());
            }

            fwrite($handle, $json);
            fflush($handle);
            flock($handle, LOCK_UN);

            return $newData;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Delete a JSON file
     *
     * @param string $filename Relative path from base path
     * @return bool
     */
    public function delete(string $filename): bool
    {
        $path = $this->resolvePath($filename);

        if (!file_exists($path)) {
            return true;
        }

        return unlink($path);
    }

    /**
     * Check if a file exists
     *
     * @param string $filename Relative path from base path
     * @return bool
     */
    public function exists(string $filename): bool
    {
        return file_exists($this->resolvePath($filename));
    }

    /**
     * List all JSON files in a directory
     *
     * @param string $directory Relative path from base path
     * @return array List of filenames (without .json extension)
     */
    public function list(string $directory = ''): array
    {
        $path = $this->resolvePath($directory);

        if (!is_dir($path)) {
            return [];
        }

        $files = [];
        $items = scandir($path);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (pathinfo($item, PATHINFO_EXTENSION) === 'json') {
                $files[] = pathinfo($item, PATHINFO_FILENAME);
            }
        }

        return $files;
    }

    /**
     * Resolve relative path to absolute path
     */
    private function resolvePath(string $filename): string
    {
        // Add .json extension if not present
        if (pathinfo($filename, PATHINFO_EXTENSION) !== 'json' && !is_dir($this->basePath . '/' . $filename)) {
            $filename .= '.json';
        }

        return $this->basePath . '/' . ltrim($filename, '/');
    }

    /**
     * Ensure directory exists
     */
    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true) && !is_dir($path)) {
                throw new \RuntimeException("Cannot create directory: {$path}");
            }
        }
    }

    /**
     * Create a backup of a file
     *
     * @param string $filename Relative path from base path
     * @return bool
     */
    public function backup(string $filename): bool
    {
        $path = $this->resolvePath($filename);

        if (!file_exists($path)) {
            return false;
        }

        $backupPath = $path . '.backup.' . date('Y-m-d_H-i-s');
        return copy($path, $backupPath);
    }
}
