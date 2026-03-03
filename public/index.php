<?php

declare(strict_types=1);

/**
 * Admin dashboard for the SmartSender Reminder System.
 *
 * Handles the HTML UI and processes form submissions (create / update /
 * cancel / delete) directly.  For programmatic access use api.php.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ReminderSystem\Database;
use ReminderSystem\Reminder;

$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['app']['timezone']);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

$error   = '';
$success = '';

try {
    $db    = Database::getInstance($config);
    $model = new Reminder($db);
} catch (\RuntimeException $e) {
    $error = 'Database connection failed: ' . htmlspecialchars($e->getMessage());
}

// ---------------------------------------------------------------------------
// Handle form submissions
// ---------------------------------------------------------------------------

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$editReminder = null;

if ($model ?? null) {
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $model->create([
                'title'          => trim($_POST['title'] ?? ''),
                'description'    => trim($_POST['description'] ?? ''),
                'email'          => trim($_POST['email'] ?? ''),
                'phone'          => trim($_POST['phone'] ?? ''),
                'event_datetime' => trim($_POST['event_datetime'] ?? ''),
            ]);
            $success = 'Reminder created successfully.';
        } catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $model->update($id, [
                'title'          => trim($_POST['title'] ?? ''),
                'description'    => trim($_POST['description'] ?? ''),
                'email'          => trim($_POST['email'] ?? ''),
                'phone'          => trim($_POST['phone'] ?? ''),
                'event_datetime' => trim($_POST['event_datetime'] ?? ''),
            ]);
            $success = 'Reminder updated successfully.';
        } catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $model->cancel($id);
        $success = 'Reminder cancelled.';
    } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $model->delete($id);
        $success = 'Reminder deleted.';
    }

    if ($editId !== null) {
        $editReminder = $model->getById($editId);
    }

    $reminders = $model->getAll();
} else {
    $reminders = [];
}

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function h(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function statusBadge(string $status): string
{
    $map = [
        'active'    => 'badge-success',
        'cancelled' => 'badge-warning',
        'completed' => 'badge-info',
    ];
    $cls = $map[$status] ?? 'badge-secondary';
    return '<span class="badge ' . $cls . '">' . h($status) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSender Reminder System – Admin</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body { padding-top: 20px; }
        .badge-success  { background-color: #28a745; color: #fff; }
        .badge-warning  { background-color: #ffc107; color: #212529; }
        .badge-info     { background-color: #17a2b8; color: #fff; }
        .sent-check     { color: #28a745; font-weight: bold; }
        .sent-cross     { color: #dc3545; }
    </style>
</head>
<body>
<div class="container">

    <h1 class="mb-4">
        📅 SmartSender Reminder System
        <small class="text-muted" style="font-size:0.5em;">Admin Dashboard</small>
    </h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <!-- ------------------------------------------------------------------ -->
    <!-- Create / Edit form                                                  -->
    <!-- ------------------------------------------------------------------ -->
    <div class="card mb-4">
        <div class="card-header">
            <?= $editReminder ? 'Edit Reminder #' . h($editReminder['id']) : 'Create New Reminder' ?>
        </div>
        <div class="card-body">
            <form method="post" action="index.php">
                <input type="hidden" name="action"
                       value="<?= $editReminder ? 'update' : 'create' ?>">
                <?php if ($editReminder): ?>
                    <input type="hidden" name="id" value="<?= h($editReminder['id']) ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Title *</label>
                        <input type="text" name="title" class="form-control" required
                               value="<?= h($editReminder['title'] ?? '') ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" required
                               value="<?= h($editReminder['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Event Date &amp; Time *</label>
                        <input type="datetime-local" name="event_datetime" class="form-control"
                               required
                               value="<?= h(
                                   isset($editReminder['event_datetime'])
                                       ? date('Y-m-d\TH:i', strtotime($editReminder['event_datetime']))
                                       : ''
                               ) ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control"
                               value="<?= h($editReminder['phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control"
                              rows="3"><?= h($editReminder['description'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <?= $editReminder ? 'Update Reminder' : 'Create Reminder' ?>
                </button>
                <?php if ($editReminder): ?>
                    <a href="index.php" class="btn btn-secondary ml-2">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ------------------------------------------------------------------ -->
    <!-- Reminders table                                                     -->
    <!-- ------------------------------------------------------------------ -->
    <h2>All Reminders (<?= count($reminders) ?>)</h2>

    <?php if (empty($reminders)): ?>
        <p class="text-muted">No reminders found. Create one above.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Email</th>
                        <th>Event Date</th>
                        <th>Status</th>
                        <th>24h</th>
                        <th>12h</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reminders as $r): ?>
                        <tr>
                            <td><?= h($r['id']) ?></td>
                            <td><?= h($r['title']) ?></td>
                            <td><?= h($r['email']) ?></td>
                            <td><?= h($r['event_datetime']) ?></td>
                            <td><?= statusBadge($r['status']) ?></td>
                            <td>
                                <?php if ($r['remind_24_sent']): ?>
                                    <span class="sent-check">✓</span>
                                <?php else: ?>
                                    <span class="sent-cross">✗</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['remind_12_sent']): ?>
                                    <span class="sent-check">✓</span>
                                <?php else: ?>
                                    <span class="sent-cross">✗</span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($r['created_at']) ?></td>
                            <td>
                                <a href="index.php?edit=<?= h($r['id']) ?>"
                                   class="btn btn-sm btn-info">Edit</a>

                                <?php if ($r['status'] === 'active'): ?>
                                    <form method="post" action="index.php"
                                          style="display:inline"
                                          onsubmit="return confirm('Cancel this reminder?')">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="id" value="<?= h($r['id']) ?>">
                                        <button class="btn btn-sm btn-warning">Cancel</button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" action="index.php"
                                      style="display:inline"
                                      onsubmit="return confirm('Permanently delete this reminder?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= h($r['id']) ?>">
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div><!-- /.container -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
