<?php
/**
 * User Model
 *
 * Handles user data management including CRUD operations and authentication.
 */

declare(strict_types=1);

namespace LauschR\Models;

use LauschR\Auth\Password;
use LauschR\Core\App;
use LauschR\Storage\JsonStore;

class User
{
    private JsonStore $store;
    private Password $password;
    private string $usersFile = 'users.json';

    public function __construct(JsonStore $store, Password $password)
    {
        $this->store = $store;
        $this->password = $password;
    }

    /**
     * Find a user by ID
     */
    public function find(string $id): ?array
    {
        $data = $this->store->read($this->usersFile, ['users' => []]);
        return $data['users'][$id] ?? null;
    }

    /**
     * Find a user by email
     */
    public function findByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        $data = $this->store->read($this->usersFile, ['users' => []]);

        foreach ($data['users'] as $user) {
            if (strtolower($user['email']) === $email) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Check if email already exists
     */
    public function emailExists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    /**
     * Get all users
     */
    public function all(): array
    {
        $data = $this->store->read($this->usersFile, ['users' => []]);
        return array_values($data['users']);
    }

    /**
     * Create a new user
     */
    public function create(array $userData): array
    {
        $id = $this->generateId();
        $now = date('c');

        // First user becomes admin, others are pending
        $isFirstUser = $this->count() === 0;

        $user = [
            'id' => $id,
            'email' => strtolower(trim($userData['email'])),
            'password_hash' => $this->password->hash($userData['password']),
            'name' => trim($userData['name']),
            'role' => $isFirstUser ? 'admin' : 'user',
            'status' => $isFirstUser ? 'approved' : 'pending',
            'created_at' => $now,
            'updated_at' => $now,
            'email_verified' => false,
            'settings' => [
                'language' => 'de',
                'notifications' => true,
            ],
        ];

        $this->store->update($this->usersFile, function ($data) use ($id, $user) {
            $data['users'] = $data['users'] ?? [];
            $data['users'][$id] = $user;
            return $data;
        }, ['users' => []]);

        // Don't return password hash
        unset($user['password_hash']);

        return $user;
    }

    /**
     * Update a user
     */
    public function update(string $id, array $userData): ?array
    {
        $updated = $this->store->update($this->usersFile, function ($data) use ($id, $userData) {
            if (!isset($data['users'][$id])) {
                return $data;
            }

            // Update allowed fields
            $allowedFields = ['name', 'email', 'settings', 'email_verified', 'role', 'status'];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $userData)) {
                    if ($field === 'email') {
                        $data['users'][$id][$field] = strtolower(trim($userData[$field]));
                    } else {
                        $data['users'][$id][$field] = $userData[$field];
                    }
                }
            }

            $data['users'][$id]['updated_at'] = date('c');

            return $data;
        }, ['users' => []]);

        $user = $updated['users'][$id] ?? null;

        if ($user) {
            unset($user['password_hash']);
        }

        return $user;
    }

    /**
     * Update user password
     */
    public function updatePassword(string $id, string $newPassword): bool
    {
        $updated = $this->store->update($this->usersFile, function ($data) use ($id, $newPassword) {
            if (!isset($data['users'][$id])) {
                return $data;
            }

            $data['users'][$id]['password_hash'] = $this->password->hash($newPassword);
            $data['users'][$id]['updated_at'] = date('c');

            return $data;
        }, ['users' => []]);

        return isset($updated['users'][$id]);
    }

    /**
     * Delete a user
     */
    public function delete(string $id): bool
    {
        $deleted = false;

        $this->store->update($this->usersFile, function ($data) use ($id, &$deleted) {
            if (isset($data['users'][$id])) {
                unset($data['users'][$id]);
                $deleted = true;
            }
            return $data;
        }, ['users' => []]);

        return $deleted;
    }

    /**
     * Verify user password
     */
    public function verifyPassword(string $id, string $password): bool
    {
        $user = $this->find($id);

        if (!$user || !isset($user['password_hash'])) {
            return false;
        }

        return $this->password->verify($password, $user['password_hash']);
    }

    /**
     * Authenticate user by email and password
     * Returns user array on success, or error code string on failure:
     * - 'invalid_credentials': wrong email or password
     * - 'pending': account awaiting approval
     * - 'rejected': account was rejected
     */
    public function authenticate(string $email, string $password): array|string
    {
        $user = $this->findByEmail($email);

        if (!$user || !isset($user['password_hash'])) {
            return 'invalid_credentials';
        }

        if (!$this->password->verify($password, $user['password_hash'])) {
            return 'invalid_credentials';
        }

        // Check user status
        $status = $user['status'] ?? 'approved'; // Default approved for legacy users
        if ($status === 'pending') {
            return 'pending';
        }
        if ($status === 'rejected') {
            return 'rejected';
        }

        // Check if password needs rehashing
        if ($this->password->needsRehash($user['password_hash'])) {
            $this->updatePassword($user['id'], $password);
        }

        // Don't return password hash
        unset($user['password_hash']);

        return $user;
    }

    /**
     * Get user's owned feeds
     */
    public function getOwnedFeeds(string $userId): array
    {
        $feedStore = new JsonStore(App::getInstance()->config('paths.feeds'));
        $feedIds = $feedStore->list();
        $ownedFeeds = [];

        foreach ($feedIds as $feedId) {
            $feed = $feedStore->read($feedId);
            if (isset($feed['owner_id']) && $feed['owner_id'] === $userId) {
                $ownedFeeds[] = $feed;
            }
        }

        return $ownedFeeds;
    }

    /**
     * Get feeds where user is a collaborator
     */
    public function getCollaboratorFeeds(string $userId): array
    {
        $feedStore = new JsonStore(App::getInstance()->config('paths.feeds'));
        $feedIds = $feedStore->list();
        $collabFeeds = [];

        foreach ($feedIds as $feedId) {
            $feed = $feedStore->read($feedId);
            if (isset($feed['collaborators'][$userId])) {
                $collabFeeds[] = $feed;
            }
        }

        return $collabFeeds;
    }

    /**
     * Get all feeds user has access to (owned + collaborator)
     */
    public function getAllAccessibleFeeds(string $userId): array
    {
        $owned = $this->getOwnedFeeds($userId);
        $collab = $this->getCollaboratorFeeds($userId);

        return array_merge($owned, $collab);
    }

    /**
     * Generate a unique user ID
     */
    private function generateId(): string
    {
        return 'usr_' . bin2hex(random_bytes(12));
    }

    /**
     * Get user count
     */
    public function count(): int
    {
        $data = $this->store->read($this->usersFile, ['users' => []]);
        return count($data['users']);
    }

    /**
     * Search users by name or email
     */
    public function search(string $query, int $limit = 10): array
    {
        $query = strtolower(trim($query));
        $data = $this->store->read($this->usersFile, ['users' => []]);
        $results = [];

        foreach ($data['users'] as $user) {
            if (
                str_contains(strtolower($user['name']), $query) ||
                str_contains(strtolower($user['email']), $query)
            ) {
                unset($user['password_hash']);
                $results[] = $user;

                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(string $id): bool
    {
        $user = $this->find($id);
        return $user && ($user['role'] ?? 'user') === 'admin';
    }

    /**
     * Check if user is moderator or admin
     */
    public function isModerator(string $id): bool
    {
        $user = $this->find($id);
        $role = $user['role'] ?? 'user';
        return $user && in_array($role, ['admin', 'moderator']);
    }

    /**
     * Check if user is approved
     */
    public function isApproved(string $id): bool
    {
        $user = $this->find($id);
        return $user && ($user['status'] ?? 'pending') === 'approved';
    }

    /**
     * Approve a user
     */
    public function approve(string $id): bool
    {
        return $this->update($id, ['status' => 'approved']) !== null;
    }

    /**
     * Reject a user
     */
    public function reject(string $id): bool
    {
        return $this->update($id, ['status' => 'rejected']) !== null;
    }

    /**
     * Set user role
     */
    public function setRole(string $id, string $role): bool
    {
        if (!in_array($role, ['admin', 'moderator', 'user'])) {
            return false;
        }
        return $this->update($id, ['role' => $role]) !== null;
    }

    /**
     * Get all pending users
     */
    public function getPending(): array
    {
        $data = $this->store->read($this->usersFile, ['users' => []]);
        $pending = [];

        foreach ($data['users'] as $user) {
            if (($user['status'] ?? 'pending') === 'pending') {
                unset($user['password_hash']);
                $pending[] = $user;
            }
        }

        return $pending;
    }

    /**
     * Get users by status
     */
    public function getByStatus(string $status): array
    {
        $data = $this->store->read($this->usersFile, ['users' => []]);
        $results = [];

        foreach ($data['users'] as $user) {
            if (($user['status'] ?? 'pending') === $status) {
                unset($user['password_hash']);
                $results[] = $user;
            }
        }

        return $results;
    }
}
