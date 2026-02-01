<?php
/**
 * Permission Model
 *
 * Handles permission checking for feeds with Owner/Editor/Viewer roles.
 */

declare(strict_types=1);

namespace LauschR\Models;

use LauschR\Core\App;

class Permission
{
    public const OWNER = 'owner';
    public const EDITOR = 'editor';
    public const CONTRIBUTOR = 'contributor';
    public const VIEWER = 'viewer';

    private array $permissionConfig;

    public function __construct()
    {
        $this->permissionConfig = App::getInstance()->config('permissions', []);
    }

    /**
     * Get all valid roles
     */
    public static function roles(): array
    {
        return [self::OWNER, self::EDITOR, self::CONTRIBUTOR, self::VIEWER];
    }

    /**
     * Check if a role is valid
     */
    public static function isValidRole(string $role): bool
    {
        return in_array($role, self::roles(), true);
    }

    /**
     * Get the user's role for a feed
     */
    public function getUserRole(array $feed, string $userId): ?string
    {
        // Check if owner
        if (isset($feed['owner_id']) && $feed['owner_id'] === $userId) {
            return self::OWNER;
        }

        // Check if collaborator
        if (isset($feed['collaborators'][$userId])) {
            return $feed['collaborators'][$userId];
        }

        return null;
    }

    /**
     * Check if user has access to a feed
     */
    public function hasAccess(array $feed, string $userId): bool
    {
        return $this->getUserRole($feed, $userId) !== null;
    }

    /**
     * Check if user is the owner
     */
    public function isOwner(array $feed, string $userId): bool
    {
        return $this->getUserRole($feed, $userId) === self::OWNER;
    }

    /**
     * Check if user is at least an editor (owner or editor)
     */
    public function isEditor(array $feed, string $userId): bool
    {
        $role = $this->getUserRole($feed, $userId);
        return $role === self::OWNER || $role === self::EDITOR;
    }

    /**
     * Check if user can perform a specific action
     */
    public function can(string $action, array $feed, string $userId): bool
    {
        $role = $this->getUserRole($feed, $userId);

        if ($role === null) {
            return false;
        }

        $rolePermissions = $this->permissionConfig[$role] ?? [];

        return $rolePermissions[$action] ?? false;
    }

    /**
     * Check if user can upload episodes
     */
    public function canUpload(array $feed, string $userId): bool
    {
        return $this->can('can_upload', $feed, $userId);
    }

    /**
     * Check if user can edit episodes
     */
    public function canEdit(array $feed, string $userId): bool
    {
        return $this->can('can_edit', $feed, $userId);
    }

    /**
     * Check if user can delete episodes
     */
    public function canDelete(array $feed, string $userId): bool
    {
        return $this->can('can_delete', $feed, $userId);
    }

    /**
     * Check if user can invite collaborators
     */
    public function canInvite(array $feed, string $userId): bool
    {
        return $this->can('can_invite', $feed, $userId);
    }

    /**
     * Check if user can manage feed settings
     */
    public function canManageSettings(array $feed, string $userId): bool
    {
        return $this->can('can_manage_settings', $feed, $userId);
    }

    /**
     * Check if user can delete the feed
     */
    public function canDeleteFeed(array $feed, string $userId): bool
    {
        return $this->can('can_delete_feed', $feed, $userId);
    }

    /**
     * Get all permissions for a user on a feed
     */
    public function getPermissions(array $feed, string $userId): array
    {
        $role = $this->getUserRole($feed, $userId);

        if ($role === null) {
            return [
                'role' => null,
                'can_upload' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_invite' => false,
                'can_manage_settings' => false,
                'can_delete_feed' => false,
            ];
        }

        $rolePermissions = $this->permissionConfig[$role] ?? [];

        return [
            'role' => $role,
            'can_upload' => $rolePermissions['can_upload'] ?? false,
            'can_edit' => $rolePermissions['can_edit'] ?? false,
            'can_delete' => $rolePermissions['can_delete'] ?? false,
            'can_invite' => $rolePermissions['can_invite'] ?? false,
            'can_manage_settings' => $rolePermissions['can_manage_settings'] ?? false,
            'can_delete_feed' => $rolePermissions['can_delete_feed'] ?? false,
        ];
    }

    /**
     * Get human-readable role name
     */
    public static function getRoleName(string $role): string
    {
        return match ($role) {
            self::OWNER => 'Besitzer',
            self::EDITOR => 'Bearbeiter',
            self::CONTRIBUTOR => 'Mitwirkender',
            self::VIEWER => 'Betrachter',
            default => 'Unbekannt',
        };
    }

    /**
     * Get role description
     */
    public static function getRoleDescription(string $role): string
    {
        return match ($role) {
            self::OWNER => 'Volle Kontrolle über den Feed einschließlich Einstellungen und Einladungen',
            self::EDITOR => 'Kann Episoden hinzufügen, bearbeiten und löschen',
            self::CONTRIBUTOR => 'Kann nur neue Episoden hinzufügen',
            self::VIEWER => 'Kann Episoden und Feed-Details ansehen',
            default => '',
        };
    }

    /**
     * Get permission level for comparing roles
     */
    public function getLevel(string $role): int
    {
        return $this->permissionConfig[$role]['level'] ?? 0;
    }

    /**
     * Check if one role is higher than another
     */
    public function isHigherRole(string $role1, string $role2): bool
    {
        return $this->getLevel($role1) > $this->getLevel($role2);
    }

    /**
     * Get all collaborators with their roles
     */
    public function getCollaborators(array $feed): array
    {
        return $feed['collaborators'] ?? [];
    }
}
