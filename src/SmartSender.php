<?php

declare(strict_types=1);

namespace ReminderSystem;

use PDO;
use RuntimeException;

/**
 * Handles communication with the SmartSender REST API.
 *
 * SmartSender is used to trigger pre-configured email workflows by
 * assigning a tag to a contact.  This class encapsulates the two tags
 * used by the reminder system:
 *
 *   REMIND_24  – sent ~24 hours before the event
 *   REMIND_12  – sent ~12 hours before the event
 */
class SmartSender
{
    public const TAG_24H = 'REMIND_24';
    public const TAG_12H = 'REMIND_12';

    private string $apiUrl;
    private string $apiKey;
    private PDO $db;

    public function __construct(string $apiUrl, string $apiKey, PDO $db)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
        $this->db     = $db;
    }

    /**
     * Send the 24-hour reminder for the given reminder row.
     *
     * @param array<string,mixed> $reminder
     */
    public function send24hReminder(array $reminder): bool
    {
        return $this->sendTag($reminder, self::TAG_24H);
    }

    /**
     * Send the 12-hour reminder for the given reminder row.
     *
     * @param array<string,mixed> $reminder
     */
    public function send12hReminder(array $reminder): bool
    {
        return $this->sendTag($reminder, self::TAG_12H);
    }

    /**
     * Trigger a SmartSender tag for a contact.
     *
     * The API call upserts the contact (identified by email) and then
     * attaches the supplied tag so that SmartSender's automation can
     * fire the appropriate email workflow.
     *
     * @param array<string,mixed> $reminder
     */
    public function sendTag(array $reminder, string $tag): bool
    {
        $payload = [
            'email'      => $reminder['email'],
            'phone'      => $reminder['phone'] ?? null,
            'tags'       => [$tag],
            'fields'     => [
                'reminder_title'       => $reminder['title'],
                'reminder_description' => $reminder['description'] ?? '',
                'event_datetime'       => $reminder['event_datetime'],
            ],
        ];

        $responseBody = $this->httpPost('/contacts/upsert-with-tag', $payload);
        $success      = $responseBody !== null;

        $this->logSend((int)$reminder['id'], $tag, $success, $responseBody);

        return $success;
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * POST JSON to a SmartSender API endpoint.
     *
     * @param array<string,mixed> $payload
     * @return string|null  Raw response body on success, null on failure.
     */
    private function httpPost(string $path, array $payload): ?string
    {
        $url  = $this->apiUrl . $path;
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '') {
            return null;
        }

        // Accept 2xx responses as successful
        if ($httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        return (string)$response;
    }

    /**
     * Persist a delivery attempt to the `reminder_logs` table.
     */
    private function logSend(int $reminderId, string $tag, bool $success, ?string $response): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO reminder_logs (reminder_id, tag, success, response)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$reminderId, $tag, (int)$success, $response]);
    }
}
