<?php
/**
 * Episode Model
 *
 * Handles podcast episode management including CRUD operations and file handling.
 */

declare(strict_types=1);

namespace LauschR\Models;

use LauschR\Core\App;
use LauschR\Security\Validator;
use LauschR\Storage\JsonStore;

class Episode
{
    private JsonStore $store;
    private string $feedsPath;
    private array $allowedMimeTypes;
    private array $allowedExtensions;
    private int $maxFileSize;

    public function __construct(JsonStore $store)
    {
        $this->store = $store;
        $this->feedsPath = 'feeds';

        $app = App::getInstance();
        $this->allowedMimeTypes = $app->config('upload.allowed_types', []);
        $this->allowedExtensions = $app->config('upload.allowed_extensions', []);
        $this->maxFileSize = $app->config('upload.max_file_size', 200 * 1024 * 1024);
    }

    /**
     * Find an episode by ID within a feed
     */
    public function find(string $feedId, string $episodeId): ?array
    {
        $feed = $this->store->read($this->feedsPath . '/' . $feedId);

        if (!$feed || !isset($feed['episodes'])) {
            return null;
        }

        foreach ($feed['episodes'] as $episode) {
            if ($episode['id'] === $episodeId) {
                return $episode;
            }
        }

        return null;
    }

    /**
     * Get all episodes for a feed
     */
    public function getAllForFeed(string $feedId): array
    {
        $feed = $this->store->read($this->feedsPath . '/' . $feedId);

        if (!$feed) {
            return [];
        }

        $episodes = $feed['episodes'] ?? [];

        // Sort by publish_date descending
        usort($episodes, fn($a, $b) => strcmp($b['publish_date'] ?? '', $a['publish_date'] ?? ''));

        return $episodes;
    }

    /**
     * Create a new episode
     */
    public function create(string $feedId, array $episodeData, array $uploadedFile): ?array
    {
        $feedPath = $this->feedsPath . '/' . $feedId;
        $feed = $this->store->read($feedPath);

        if (!$feed) {
            return null;
        }

        // Validate and process the uploaded file
        $fileResult = $this->processUpload($feedId, $uploadedFile);

        if (!$fileResult['success']) {
            throw new \RuntimeException($fileResult['error']);
        }

        $id = $this->generateId();
        $now = date('c');

        $episode = [
            'id' => $id,
            'guid' => $id,
            'title' => trim($episodeData['title']),
            'description' => trim($episodeData['description'] ?? ''),
            'author' => trim($episodeData['author'] ?? $feed['author'] ?? ''),
            'duration' => (int)($episodeData['duration'] ?? 0),
            'explicit' => (bool)($episodeData['explicit'] ?? false),
            'publish_date' => $episodeData['publish_date'] ?? $now,
            'audio_file' => $fileResult['filename'],
            'audio_url' => $fileResult['url'],
            'file_size' => $fileResult['size'],
            'mime_type' => $fileResult['mime_type'],
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $episodeData['created_by'] ?? null,
        ];

        // Add episode to feed
        $this->store->update($feedPath, function ($feed) use ($episode) {
            $feed['episodes'] = $feed['episodes'] ?? [];
            array_unshift($feed['episodes'], $episode); // Add to beginning
            $feed['updated_at'] = date('c');
            return $feed;
        });

        return $episode;
    }

    /**
     * Update an episode
     */
    public function update(string $feedId, string $episodeId, array $episodeData): ?array
    {
        $feedPath = $this->feedsPath . '/' . $feedId;

        $updated = $this->store->update($feedPath, function ($feed) use ($episodeId, $episodeData) {
            if (!isset($feed['episodes'])) {
                return $feed;
            }

            foreach ($feed['episodes'] as &$episode) {
                if ($episode['id'] === $episodeId) {
                    $allowedFields = ['title', 'description', 'author', 'duration', 'explicit', 'publish_date', 'status'];

                    foreach ($allowedFields as $field) {
                        if (array_key_exists($field, $episodeData)) {
                            if (is_string($episodeData[$field])) {
                                $episode[$field] = trim($episodeData[$field]);
                            } else {
                                $episode[$field] = $episodeData[$field];
                            }
                        }
                    }

                    $episode['updated_at'] = date('c');
                    break;
                }
            }

            $feed['updated_at'] = date('c');

            return $feed;
        });

        return $this->find($feedId, $episodeId);
    }

    /**
     * Delete an episode
     */
    public function delete(string $feedId, string $episodeId): bool
    {
        $episode = $this->find($feedId, $episodeId);

        if (!$episode) {
            return false;
        }

        // Delete audio file
        if (!empty($episode['audio_file'])) {
            $audioPath = App::getInstance()->config('paths.audio') . '/' . $feedId . '/' . $episode['audio_file'];
            if (file_exists($audioPath)) {
                unlink($audioPath);
            }
        }

        $feedPath = $this->feedsPath . '/' . $feedId;
        $deleted = false;

        $this->store->update($feedPath, function ($feed) use ($episodeId, &$deleted) {
            if (!isset($feed['episodes'])) {
                return $feed;
            }

            $originalCount = count($feed['episodes']);
            $feed['episodes'] = array_values(array_filter(
                $feed['episodes'],
                fn($ep) => $ep['id'] !== $episodeId
            ));

            $deleted = count($feed['episodes']) < $originalCount;
            $feed['updated_at'] = date('c');

            return $feed;
        });

        return $deleted;
    }

    /**
     * Process an uploaded audio file
     */
    public function processUpload(string $feedId, array $file): array
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'error' => $this->getUploadErrorMessage($file['error']),
            ];
        }

        // Validate file size
        if ($file['size'] > $this->maxFileSize) {
            return [
                'success' => false,
                'error' => 'Die Datei ist zu groß. Maximum: ' . $this->formatBytes($this->maxFileSize),
            ];
        }

        // Validate file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions, true)) {
            return [
                'success' => false,
                'error' => 'Ungültiger Dateityp. Erlaubt: ' . implode(', ', $this->allowedExtensions),
            ];
        }

        // Validate MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
            return [
                'success' => false,
                'error' => 'Ungültiger MIME-Typ: ' . $mimeType,
            ];
        }

        // Generate unique filename
        $slug = Validator::slugify(pathinfo($file['name'], PATHINFO_FILENAME));
        $timestamp = date('Ymd-His');
        $filename = "{$slug}-{$timestamp}.{$extension}";

        // Ensure audio directory exists
        $audioDir = App::getInstance()->config('paths.audio') . '/' . $feedId;
        if (!is_dir($audioDir)) {
            mkdir($audioDir, 0755, true);
        }

        $destination = $audioDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return [
                'success' => false,
                'error' => 'Fehler beim Speichern der Datei.',
            ];
        }

        // Generate URL
        $baseUrl = App::getInstance()->config('app.url');
        $url = rtrim($baseUrl, '/') . '/audio/' . $feedId . '/' . $filename;

        return [
            'success' => true,
            'filename' => $filename,
            'url' => $url,
            'size' => $file['size'],
            'mime_type' => $mimeType,
        ];
    }

    /**
     * Replace an episode's audio file
     */
    public function replaceAudio(string $feedId, string $episodeId, array $uploadedFile): ?array
    {
        $episode = $this->find($feedId, $episodeId);

        if (!$episode) {
            return null;
        }

        // Delete old audio file
        if (!empty($episode['audio_file'])) {
            $oldPath = App::getInstance()->config('paths.audio') . '/' . $feedId . '/' . $episode['audio_file'];
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        // Process new file
        $fileResult = $this->processUpload($feedId, $uploadedFile);

        if (!$fileResult['success']) {
            throw new \RuntimeException($fileResult['error']);
        }

        // Update episode
        $feedPath = $this->feedsPath . '/' . $feedId;

        $this->store->update($feedPath, function ($feed) use ($episodeId, $fileResult) {
            foreach ($feed['episodes'] as &$episode) {
                if ($episode['id'] === $episodeId) {
                    $episode['audio_file'] = $fileResult['filename'];
                    $episode['audio_url'] = $fileResult['url'];
                    $episode['file_size'] = $fileResult['size'];
                    $episode['mime_type'] = $fileResult['mime_type'];
                    $episode['updated_at'] = date('c');
                    break;
                }
            }
            return $feed;
        });

        return $this->find($feedId, $episodeId);
    }

    /**
     * Reorder episodes in a feed
     */
    public function reorder(string $feedId, array $episodeIds): bool
    {
        $feedPath = $this->feedsPath . '/' . $feedId;

        $this->store->update($feedPath, function ($feed) use ($episodeIds) {
            if (!isset($feed['episodes'])) {
                return $feed;
            }

            // Create a map of episodes by ID
            $episodeMap = [];
            foreach ($feed['episodes'] as $episode) {
                $episodeMap[$episode['id']] = $episode;
            }

            // Reorder based on provided IDs
            $reordered = [];
            foreach ($episodeIds as $id) {
                if (isset($episodeMap[$id])) {
                    $reordered[] = $episodeMap[$id];
                    unset($episodeMap[$id]);
                }
            }

            // Add any remaining episodes not in the order list
            foreach ($episodeMap as $episode) {
                $reordered[] = $episode;
            }

            $feed['episodes'] = $reordered;
            $feed['updated_at'] = date('c');

            return $feed;
        });

        return true;
    }

    /**
     * Get episode count for a feed
     */
    public function count(string $feedId): int
    {
        $feed = $this->store->read($this->feedsPath . '/' . $feedId);

        if (!$feed || !isset($feed['episodes'])) {
            return 0;
        }

        return count($feed['episodes']);
    }

    /**
     * Get latest episodes across all accessible feeds
     */
    public function getLatest(array $feedIds, int $limit = 10): array
    {
        $allEpisodes = [];

        foreach ($feedIds as $feedId) {
            $episodes = $this->getAllForFeed($feedId);

            foreach ($episodes as $episode) {
                $episode['feed_id'] = $feedId;
                $allEpisodes[] = $episode;
            }
        }

        // Sort by publish_date descending
        usort($allEpisodes, fn($a, $b) => strcmp($b['publish_date'] ?? '', $a['publish_date'] ?? ''));

        return array_slice($allEpisodes, 0, $limit);
    }

    /**
     * Generate a unique episode ID
     */
    private function generateId(): string
    {
        return 'ep_' . bin2hex(random_bytes(12));
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'Die Datei überschreitet die maximale Upload-Größe.',
            UPLOAD_ERR_FORM_SIZE => 'Die Datei überschreitet die maximale Formulargröße.',
            UPLOAD_ERR_PARTIAL => 'Die Datei wurde nur teilweise hochgeladen.',
            UPLOAD_ERR_NO_FILE => 'Keine Datei hochgeladen.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Verzeichnis fehlt.',
            UPLOAD_ERR_CANT_WRITE => 'Fehler beim Schreiben der Datei.',
            UPLOAD_ERR_EXTENSION => 'Upload durch Erweiterung gestoppt.',
            default => 'Unbekannter Upload-Fehler.',
        };
    }

    /**
     * Format bytes to human-readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Format duration in seconds to HH:MM:SS
     */
    public static function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * Parse duration string to seconds
     */
    public static function parseDuration(string $duration): int
    {
        $parts = array_reverse(explode(':', $duration));
        $seconds = 0;
        $multipliers = [1, 60, 3600];

        foreach ($parts as $i => $part) {
            if (isset($multipliers[$i])) {
                $seconds += (int)$part * $multipliers[$i];
            }
        }

        return $seconds;
    }
}
