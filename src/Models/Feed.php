<?php
/**
 * Feed Model
 *
 * Handles podcast feed management including CRUD operations and collaborator management.
 */

declare(strict_types=1);

namespace LauschR\Models;

use LauschR\Core\App;
use LauschR\Security\Validator;
use LauschR\Storage\JsonStore;

class Feed
{
    private JsonStore $store;
    private Permission $permission;
    private string $feedsPath;

    public function __construct(JsonStore $store, Permission $permission)
    {
        $this->store = $store;
        $this->permission = $permission;
        $this->feedsPath = 'feeds';
    }

    /**
     * Find a feed by ID
     */
    public function find(string $id): ?array
    {
        $path = $this->feedsPath . '/' . $id;

        if (!$this->store->exists($path)) {
            return null;
        }

        return $this->store->read($path);
    }

    /**
     * Find a feed by slug
     */
    public function findBySlug(string $slug): ?array
    {
        $feedIds = $this->store->list($this->feedsPath);

        foreach ($feedIds as $feedId) {
            $feed = $this->store->read($this->feedsPath . '/' . $feedId);
            if (isset($feed['slug']) && $feed['slug'] === $slug) {
                return $feed;
            }
        }

        return null;
    }

    /**
     * Get all feeds
     */
    public function all(): array
    {
        $feedIds = $this->store->list($this->feedsPath);
        $feeds = [];

        foreach ($feedIds as $feedId) {
            $feed = $this->store->read($this->feedsPath . '/' . $feedId);
            if ($feed) {
                $feeds[] = $feed;
            }
        }

        // Sort by created_at descending
        usort($feeds, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return $feeds;
    }

    /**
     * Get feeds owned by a user
     */
    public function getByOwner(string $userId): array
    {
        $feeds = $this->all();

        return array_values(array_filter($feeds, fn($feed) => ($feed['owner_id'] ?? null) === $userId));
    }

    /**
     * Get feeds where user is a collaborator
     */
    public function getByCollaborator(string $userId): array
    {
        $feeds = $this->all();

        return array_values(array_filter($feeds, fn($feed) => isset($feed['collaborators'][$userId])));
    }

    /**
     * Get all feeds accessible by a user (owned + collaborator)
     */
    public function getAccessibleByUser(string $userId): array
    {
        $feeds = $this->all();

        return array_values(array_filter($feeds, function ($feed) use ($userId) {
            return ($feed['owner_id'] ?? null) === $userId || isset($feed['collaborators'][$userId]);
        }));
    }

    /**
     * Create a new feed
     */
    public function create(array $feedData, string $ownerId): array
    {
        $id = $this->generateId();
        $now = date('c');
        $slug = $this->generateUniqueSlug($feedData['title']);

        $feed = [
            'id' => $id,
            'slug' => $slug,
            'owner_id' => $ownerId,
            'title' => trim($feedData['title']),
            'description' => trim($feedData['description'] ?? ''),
            'author' => trim($feedData['author'] ?? ''),
            'email' => trim($feedData['email'] ?? ''),
            'language' => $feedData['language'] ?? 'de',
            'explicit' => (bool)($feedData['explicit'] ?? false),
            'image' => $feedData['image'] ?? null,
            'website' => trim($feedData['website'] ?? ''),
            'category' => $feedData['category'] ?? '',
            'collaborators' => [],
            'episodes' => [],
            'settings' => [
                'is_public' => (bool)($feedData['is_public'] ?? true),
                'require_auth' => (bool)($feedData['require_auth'] ?? false),
            ],
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->store->write($this->feedsPath . '/' . $id, $feed);

        // Create audio directory for this feed
        $audioPath = App::getInstance()->config('paths.audio') . '/' . $id;
        if (!is_dir($audioPath)) {
            mkdir($audioPath, 0755, true);
        }

        return $feed;
    }

    /**
     * Update a feed
     */
    public function update(string $id, array $feedData): ?array
    {
        $path = $this->feedsPath . '/' . $id;

        if (!$this->store->exists($path)) {
            return null;
        }

        $feed = $this->store->update($path, function ($current) use ($feedData) {
            $allowedFields = [
                'title', 'description', 'author', 'email', 'language',
                'explicit', 'image', 'website', 'category', 'settings'
            ];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $feedData)) {
                    if (is_string($feedData[$field])) {
                        $current[$field] = trim($feedData[$field]);
                    } else {
                        $current[$field] = $feedData[$field];
                    }
                }
            }

            // Update slug if title changed
            if (isset($feedData['title']) && $feedData['title'] !== ($current['title'] ?? '')) {
                $current['slug'] = $this->generateUniqueSlug($feedData['title'], $current['id']);
            }

            $current['updated_at'] = date('c');

            return $current;
        });

        return $feed;
    }

    /**
     * Delete a feed
     */
    public function delete(string $id): bool
    {
        $path = $this->feedsPath . '/' . $id;

        if (!$this->store->exists($path)) {
            return false;
        }

        // Delete audio files
        $audioPath = App::getInstance()->config('paths.audio') . '/' . $id;
        if (is_dir($audioPath)) {
            $this->deleteDirectory($audioPath);
        }

        return $this->store->delete($path);
    }

    /**
     * Add a collaborator to a feed
     */
    public function addCollaborator(string $feedId, string $userId, string $role = Permission::EDITOR): ?array
    {
        if (!Permission::isValidRole($role) || $role === Permission::OWNER) {
            return null;
        }

        $path = $this->feedsPath . '/' . $feedId;

        if (!$this->store->exists($path)) {
            return null;
        }

        return $this->store->update($path, function ($feed) use ($userId, $role) {
            // Can't add owner as collaborator
            if (($feed['owner_id'] ?? null) === $userId) {
                return $feed;
            }

            $feed['collaborators'] = $feed['collaborators'] ?? [];
            $feed['collaborators'][$userId] = $role;
            $feed['updated_at'] = date('c');

            return $feed;
        });
    }

    /**
     * Update collaborator role
     */
    public function updateCollaboratorRole(string $feedId, string $userId, string $role): ?array
    {
        if (!Permission::isValidRole($role) || $role === Permission::OWNER) {
            return null;
        }

        $path = $this->feedsPath . '/' . $feedId;

        if (!$this->store->exists($path)) {
            return null;
        }

        return $this->store->update($path, function ($feed) use ($userId, $role) {
            if (!isset($feed['collaborators'][$userId])) {
                return $feed;
            }

            $feed['collaborators'][$userId] = $role;
            $feed['updated_at'] = date('c');

            return $feed;
        });
    }

    /**
     * Remove a collaborator from a feed
     */
    public function removeCollaborator(string $feedId, string $userId): ?array
    {
        $path = $this->feedsPath . '/' . $feedId;

        if (!$this->store->exists($path)) {
            return null;
        }

        return $this->store->update($path, function ($feed) use ($userId) {
            unset($feed['collaborators'][$userId]);
            $feed['updated_at'] = date('c');

            return $feed;
        });
    }

    /**
     * Transfer ownership to another user
     */
    public function transferOwnership(string $feedId, string $newOwnerId, string $currentOwnerId): ?array
    {
        $path = $this->feedsPath . '/' . $feedId;

        if (!$this->store->exists($path)) {
            return null;
        }

        return $this->store->update($path, function ($feed) use ($newOwnerId, $currentOwnerId) {
            // Verify current owner
            if (($feed['owner_id'] ?? null) !== $currentOwnerId) {
                return $feed;
            }

            // Remove new owner from collaborators if present
            unset($feed['collaborators'][$newOwnerId]);

            // Add current owner as editor
            $feed['collaborators'][$currentOwnerId] = Permission::EDITOR;

            // Set new owner
            $feed['owner_id'] = $newOwnerId;
            $feed['updated_at'] = date('c');

            return $feed;
        });
    }

    /**
     * Get feed statistics
     */
    public function getStats(string $feedId): array
    {
        $feed = $this->find($feedId);

        if (!$feed) {
            return [];
        }

        $episodes = $feed['episodes'] ?? [];
        $totalDuration = 0;

        foreach ($episodes as $episode) {
            $totalDuration += $episode['duration'] ?? 0;
        }

        return [
            'episode_count' => count($episodes),
            'collaborator_count' => count($feed['collaborators'] ?? []),
            'total_duration' => $totalDuration,
            'last_episode' => !empty($episodes) ? $episodes[0]['created_at'] ?? null : null,
        ];
    }

    /**
     * Check if a slug is unique
     */
    public function isSlugUnique(string $slug, ?string $excludeId = null): bool
    {
        $feedIds = $this->store->list($this->feedsPath);

        foreach ($feedIds as $feedId) {
            if ($excludeId && $feedId === $excludeId) {
                continue;
            }

            $feed = $this->store->read($this->feedsPath . '/' . $feedId);
            if (isset($feed['slug']) && $feed['slug'] === $slug) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate a unique slug
     */
    private function generateUniqueSlug(string $title, ?string $excludeId = null): string
    {
        $baseSlug = Validator::slugify($title);

        if (empty($baseSlug)) {
            $baseSlug = 'feed';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (!$this->isSlugUnique($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Generate a unique feed ID
     */
    private function generateId(): string
    {
        return 'feed_' . bin2hex(random_bytes(12));
    }

    /**
     * Delete a directory and its contents
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    /**
     * Get the RSS feed URL for a feed
     */
    public function getRssUrl(array $feed): string
    {
        $baseUrl = App::getInstance()->config('app.url');
        return rtrim($baseUrl, '/') . '/feed/' . $feed['slug'] . '/rss.xml';
    }

    /**
     * Get the public page URL for a feed
     */
    public function getPublicUrl(array $feed): string
    {
        $baseUrl = App::getInstance()->config('app.url');
        return rtrim($baseUrl, '/') . '/feed/' . $feed['slug'];
    }
}
