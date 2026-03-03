<?php

declare(strict_types=1);

/**
 * REST API for managing reminders.
 *
 * Endpoints:
 *   GET    /api.php          – list all reminders
 *   GET    /api.php?id=N     – get one reminder
 *   POST   /api.php          – create a reminder  (JSON body)
 *   PUT    /api.php?id=N     – update a reminder  (JSON body)
 *   DELETE /api.php?id=N     – delete a reminder
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ReminderSystem\Database;
use ReminderSystem\Reminder;

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['app']['timezone']);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function jsonResponse(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function jsonError(string $message, int $status = 400): never
{
    jsonResponse(['error' => $message], $status);
}

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

try {
    $db = Database::getInstance($config);
} catch (\RuntimeException $e) {
    jsonError('Database connection failed.', 503);
}

$model  = new Reminder($db);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ---------------------------------------------------------------------------
// Route
// ---------------------------------------------------------------------------

switch ($method) {
    case 'GET':
        if ($id !== null) {
            $reminder = $model->getById($id);
            if ($reminder === null) {
                jsonError('Reminder not found.', 404);
            }
            jsonResponse($reminder);
        }
        jsonResponse($model->getAll());

    case 'POST':
        $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        try {
            $newId = $model->create($body);
            jsonResponse(['id' => $newId, 'message' => 'Reminder created.'], 201);
        } catch (\InvalidArgumentException $e) {
            jsonError($e->getMessage());
        }

    case 'PUT':
        if ($id === null) {
            jsonError('Missing id parameter.');
        }
        if ($model->getById($id) === null) {
            jsonError('Reminder not found.', 404);
        }
        $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        try {
            $model->update($id, $body);
            jsonResponse(['message' => 'Reminder updated.']);
        } catch (\InvalidArgumentException $e) {
            jsonError($e->getMessage());
        }

    case 'DELETE':
        if ($id === null) {
            jsonError('Missing id parameter.');
        }
        if ($model->getById($id) === null) {
            jsonError('Reminder not found.', 404);
        }
        $model->delete($id);
        jsonResponse(['message' => 'Reminder deleted.']);

    default:
        jsonError('Method not allowed.', 405);
}
