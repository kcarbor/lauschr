<?php $view->startSection('content'); ?>

<div class="page-header">
    <h1 class="page-title">Benutzerverwaltung</h1>
    <div class="page-actions">
        <a href="<?= $view->url('/dashboard') ?>" class="btn btn-secondary">
            Zurück zum Dashboard
        </a>
    </div>
</div>

<?php if (!empty($pending)): ?>
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h2 class="card-title">Ausstehende Freigaben (<?= count($pending) ?>)</h2>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>E-Mail</th>
                    <th>Registriert am</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $user): ?>
                <tr>
                    <td><?= $view->e($user['name']) ?></td>
                    <td><?= $view->e($user['email']) ?></td>
                    <td><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></td>
                    <td>
                        <form method="POST" action="<?= $view->url('/admin/users/' . $user['id'] . '/approve') ?>" style="display: inline;">
                            <?= $csrf->field() ?>
                            <button type="submit" class="btn btn-success btn-sm">Freigeben</button>
                        </form>
                        <form method="POST" action="<?= $view->url('/admin/users/' . $user['id'] . '/reject') ?>" style="display: inline;">
                            <?= $csrf->field() ?>
                            <button type="submit" class="btn btn-danger btn-sm">Ablehnen</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h2 class="card-title">Aktive Benutzer (<?= count($approved) ?>)</h2>
    </div>
    <div class="card-body">
        <?php if (empty($approved)): ?>
            <p style="color: var(--color-gray-500);">Keine aktiven Benutzer vorhanden.</p>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>E-Mail</th>
                    <th>Rolle</th>
                    <th>Registriert am</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($approved as $user): ?>
                <tr>
                    <td><?= $view->e($user['name']) ?></td>
                    <td><?= $view->e($user['email']) ?></td>
                    <td>
                        <?php
                        $roleNames = ['admin' => 'Administrator', 'moderator' => 'Moderator', 'user' => 'Benutzer'];
                        $role = $user['role'] ?? 'user';
                        ?>
                        <span class="badge badge-<?= $role === 'admin' ? 'primary' : ($role === 'moderator' ? 'success' : 'secondary') ?>">
                            <?= $roleNames[$role] ?? $role ?>
                        </span>
                    </td>
                    <td><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></td>
                    <td>
                        <?php if ($currentUser['id'] !== $user['id']): ?>
                            <?php if (($currentUser['role'] ?? 'user') === 'admin'): ?>
                            <form method="POST" action="<?= $view->url('/admin/users/' . $user['id'] . '/role') ?>" style="display: inline;">
                                <?= $csrf->field() ?>
                                <select name="role" onchange="this.form.submit()" class="form-input" style="width: auto; display: inline-block; padding: 0.25rem 0.5rem;">
                                    <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Benutzer</option>
                                    <option value="moderator" <?= $role === 'moderator' ? 'selected' : '' ?>>Moderator</option>
                                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </form>
                            <form method="POST" action="<?= $view->url('/admin/users/' . $user['id'] . '/delete') ?>" style="display: inline;" onsubmit="return confirm('Benutzer wirklich löschen?');">
                                <?= $csrf->field() ?>
                                <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
                            </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: var(--color-gray-400);">(Sie)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($rejected)): ?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Abgelehnte Benutzer (<?= count($rejected) ?>)</h2>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>E-Mail</th>
                    <th>Registriert am</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rejected as $user): ?>
                <tr>
                    <td><?= $view->e($user['name']) ?></td>
                    <td><?= $view->e($user['email']) ?></td>
                    <td><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></td>
                    <td>
                        <form method="POST" action="<?= $view->url('/admin/users/' . $user['id'] . '/approve') ?>" style="display: inline;">
                            <?= $csrf->field() ?>
                            <button type="submit" class="btn btn-success btn-sm">Doch freigeben</button>
                        </form>
                        <?php if (($currentUser['role'] ?? 'user') === 'admin'): ?>
                        <form method="POST" action="<?= $view->url('/admin/users/' . $user['id'] . '/delete') ?>" style="display: inline;" onsubmit="return confirm('Benutzer endgültig löschen?');">
                            <?= $csrf->field() ?>
                            <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php $view->endSection(); ?>
