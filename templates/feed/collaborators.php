<?php $view->startSection('content'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Mitarbeiter verwalten</h1>
        <p class="text-muted"><?= $view->e($feed['title']) ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= $view->url('/feeds/' . $feed['id']) ?>" class="btn btn-secondary">
            ← Zurück zum Feed
        </a>
    </div>
</div>

<!-- Tabs -->
<div class="tabs">
    <a href="<?= $view->url('/feeds/' . $feed['id']) ?>" class="tab">Episoden</a>
    <a href="<?= $view->url('/feeds/' . $feed['id'] . '/collaborators') ?>" class="tab active">Mitarbeiter</a>
</div>

<div class="d-flex gap-6" style="flex-wrap: wrap;">
    <!-- Current Collaborators -->
    <div class="card" style="flex: 1; min-width: 300px;">
        <div class="card-header">
            <h3 class="card-title">Aktuelle Mitarbeiter</h3>
        </div>
        <div class="card-body">
            <div class="collaborator-list">
                <!-- Owner -->
                <div class="collaborator-item">
                    <div class="collaborator-avatar">
                        <?= strtoupper(substr($owner['name'] ?? 'O', 0, 1)) ?>
                    </div>
                    <div class="collaborator-info">
                        <div class="collaborator-name"><?= $view->e($owner['name']) ?></div>
                        <div class="collaborator-email"><?= $view->e($owner['email']) ?></div>
                    </div>
                    <span class="collaborator-role owner">Besitzer</span>
                </div>

                <!-- Collaborators -->
                <?php if (!empty($collaborators)): ?>
                    <?php foreach ($collaborators as $collab): ?>
                        <div class="collaborator-item">
                            <div class="collaborator-avatar">
                                <?= strtoupper(substr($collab['user']['name'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div class="collaborator-info">
                                <div class="collaborator-name"><?= $view->e($collab['user']['name']) ?></div>
                                <div class="collaborator-email"><?= $view->e($collab['user']['email']) ?></div>
                            </div>
                            <span class="collaborator-role <?= $collab['role'] ?>">
                                <?= $view->e(\LauschR\Models\Permission::getRoleName($collab['role'])) ?>
                            </span>
                            <?php if ($permission->canInvite($feed, $currentUser['id'])): ?>
                                <form method="POST" action="<?= $view->url('/feeds/' . $feed['id'] . '/collaborators/' . $collab['user']['id'] . '/remove') ?>" style="margin-left: 0.5rem;">
                                    <?= $csrf->field() ?>
                                    <button type="submit" class="btn btn-danger btn-sm btn-icon" data-confirm="Möchten Sie diesen Mitarbeiter wirklich entfernen?" title="Entfernen">
                                        ×
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (empty($collaborators)): ?>
                <p class="text-muted text-center mt-4">
                    Noch keine Mitarbeiter hinzugefügt.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invite New Collaborator -->
    <?php if ($permission->canInvite($feed, $currentUser['id'])): ?>
        <div class="card" style="flex: 1; min-width: 300px;">
            <div class="card-header">
                <h3 class="card-title">Mitarbeiter einladen</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <div><?= $view->e($error) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= $view->url('/feeds/' . $feed['id'] . '/collaborators/invite') ?>">
                    <?= $csrf->field() ?>

                    <div class="form-group">
                        <label for="collaborator-email" class="form-label form-label-required">E-Mail-Adresse</label>
                        <input
                            type="email"
                            id="collaborator-email"
                            name="email"
                            class="form-input"
                            required
                            placeholder="email@beispiel.de"
                        >
                        <p class="form-hint">
                            Der Benutzer muss bereits bei LauschR registriert sein.
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="role" class="form-label form-label-required">Rolle</label>
                        <select id="role" name="role" class="form-select" required>
                            <option value="editor">Bearbeiter - Kann Episoden erstellen, bearbeiten und löschen</option>
                            <option value="viewer">Betrachter - Kann nur Episoden ansehen</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Einladen</button>
                    </div>
                </form>

                <hr style="margin: 2rem 0; border: none; border-top: 1px solid var(--color-gray-200);">

                <h4>Rollen-Übersicht</h4>
                <table style="width: 100%; margin-top: 1rem; font-size: 0.875rem;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 1px solid var(--color-gray-200);">
                            <th style="padding: 0.5rem 0;">Berechtigung</th>
                            <th style="padding: 0.5rem; text-align: center;">Bearbeiter</th>
                            <th style="padding: 0.5rem; text-align: center;">Betrachter</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 0.5rem 0;">Episoden ansehen</td>
                            <td style="text-align: center;">✅</td>
                            <td style="text-align: center;">✅</td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem 0;">Episoden hochladen</td>
                            <td style="text-align: center;">✅</td>
                            <td style="text-align: center;">❌</td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem 0;">Episoden bearbeiten</td>
                            <td style="text-align: center;">✅</td>
                            <td style="text-align: center;">❌</td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem 0;">Episoden löschen</td>
                            <td style="text-align: center;">✅</td>
                            <td style="text-align: center;">❌</td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem 0;">Feed-Einstellungen</td>
                            <td style="text-align: center;">❌</td>
                            <td style="text-align: center;">❌</td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem 0;">Mitarbeiter einladen</td>
                            <td style="text-align: center;">❌</td>
                            <td style="text-align: center;">❌</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php $view->endSection(); ?>
