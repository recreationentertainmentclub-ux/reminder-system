<?php

declare(strict_types=1);

/**
 * Cron job – send pending reminder notifications via SmartSender.
 *
 * Intended to be triggered every 5 minutes, e.g. via GitHub Actions
 * scheduled workflow or a traditional crontab entry:
 *
 *   */5 * * * *  php /path/to/cron/send_reminders.php >> /path/to/logs/cron.log 2>&1
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ReminderSystem\Database;
use ReminderSystem\Reminder;
use ReminderSystem\SmartSender;

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

$config = require __DIR__ . '/../config/config.php';

date_default_timezone_set($config['app']['timezone']);

$logFile = $config['app']['log_file'];

function logMessage(string $message, string $logFile): void
{
    $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

// ---------------------------------------------------------------------------
// Connect
// ---------------------------------------------------------------------------

try {
    $db = Database::getInstance($config);
} catch (\RuntimeException $e) {
    logMessage('DB connection failed: ' . $e->getMessage(), $logFile);
    exit(1);
}

$reminderModel = new Reminder($db);
$smartSender   = new SmartSender(
    $config['smartsender']['api_url'],
    $config['smartsender']['api_key'],
    $db
);

logMessage('Cron run started.', $logFile);

// ---------------------------------------------------------------------------
// 24-hour reminders
// ---------------------------------------------------------------------------

$due24 = $reminderModel->getDue24hReminders();
logMessage(sprintf('Found %d reminder(s) due for 24h notification.', count($due24)), $logFile);

foreach ($due24 as $reminder) {
    $id = (int)$reminder['id'];
    try {
        $ok = $smartSender->send24hReminder($reminder);
        if ($ok) {
            $reminderModel->markRemind24Sent($id);
            logMessage("24h reminder sent OK for reminder #{$id} ({$reminder['email']}).", $logFile);
        } else {
            logMessage("24h reminder FAILED for reminder #{$id} ({$reminder['email']}).", $logFile);
        }
    } catch (\Throwable $e) {
        logMessage("Error sending 24h reminder #{$id}: " . $e->getMessage(), $logFile);
    }
}

// ---------------------------------------------------------------------------
// 12-hour reminders
// ---------------------------------------------------------------------------

$due12 = $reminderModel->getDue12hReminders();
logMessage(sprintf('Found %d reminder(s) due for 12h notification.', count($due12)), $logFile);

foreach ($due12 as $reminder) {
    $id = (int)$reminder['id'];
    try {
        $ok = $smartSender->send12hReminder($reminder);
        if ($ok) {
            $reminderModel->markRemind12Sent($id);
            logMessage("12h reminder sent OK for reminder #{$id} ({$reminder['email']}).", $logFile);
        } else {
            logMessage("12h reminder FAILED for reminder #{$id} ({$reminder['email']}).", $logFile);
        }
    } catch (\Throwable $e) {
        logMessage("Error sending 12h reminder #{$id}: " . $e->getMessage(), $logFile);
    }
}

logMessage('Cron run finished.', $logFile);
