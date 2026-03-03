<?php

declare(strict_types=1);

namespace ReminderSystem;

use PDO;
use InvalidArgumentException;

/**
 * Active-record-style model for the `reminders` table.
 */
class Reminder
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ------------------------------------------------------------------
    // Read
    // ------------------------------------------------------------------

    /**
     * Return all reminders, newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAll(): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM reminders ORDER BY event_datetime DESC'
        );
        return $stmt->fetchAll();
    }

    /**
     * Return a single reminder by its primary key.
     *
     * @return array<string,mixed>|null
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM reminders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Return active reminders whose 24-hour notification has not been sent
     * and whose event is between now and 25 hours from now.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getDue24hReminders(): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM reminders
              WHERE status = 'active'
                AND remind_24_sent = 0
                AND event_datetime BETWEEN DATE_ADD(NOW(), INTERVAL 23 HOUR)
                                      AND DATE_ADD(NOW(), INTERVAL 25 HOUR)"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Return active reminders whose 12-hour notification has not been sent
     * and whose event is between now and 13 hours from now.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getDue12hReminders(): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM reminders
              WHERE status = 'active'
                AND remind_12_sent = 0
                AND event_datetime BETWEEN DATE_ADD(NOW(), INTERVAL 11 HOUR)
                                      AND DATE_ADD(NOW(), INTERVAL 13 HOUR)"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------
    // Write
    // ------------------------------------------------------------------

    /**
     * Insert a new reminder and return its new ID.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $this->validate($data);

        $stmt = $this->db->prepare(
            'INSERT INTO reminders (title, description, email, phone, event_datetime)
             VALUES (:title, :description, :email, :phone, :event_datetime)'
        );
        $stmt->execute([
            ':title'          => $data['title'],
            ':description'    => $data['description'] ?? null,
            ':email'          => $data['email'],
            ':phone'          => $data['phone'] ?? null,
            ':event_datetime' => $data['event_datetime'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update an existing reminder.
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $this->validate($data);

        $stmt = $this->db->prepare(
            'UPDATE reminders
                SET title          = :title,
                    description    = :description,
                    email          = :email,
                    phone          = :phone,
                    event_datetime = :event_datetime
              WHERE id = :id'
        );

        return $stmt->execute([
            ':title'          => $data['title'],
            ':description'    => $data['description'] ?? null,
            ':email'          => $data['email'],
            ':phone'          => $data['phone'] ?? null,
            ':event_datetime' => $data['event_datetime'],
            ':id'             => $id,
        ]);
    }

    /**
     * Soft-cancel a reminder (keeps it in the DB for auditing).
     */
    public function cancel(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE reminders SET status = 'cancelled' WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    /**
     * Delete a reminder permanently.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM reminders WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Mark the 24-hour reminder as sent.
     */
    public function markRemind24Sent(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE reminders SET remind_24_sent = 1 WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    /**
     * Mark the 12-hour reminder as sent.
     */
    public function markRemind12Sent(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE reminders SET remind_12_sent = 1 WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Basic validation – throws InvalidArgumentException on failure.
     *
     * @param array<string,mixed> $data
     */
    private function validate(array $data): void
    {
        if (empty($data['title'])) {
            throw new InvalidArgumentException('Title is required.');
        }
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required.');
        }
        if (empty($data['event_datetime'])) {
            throw new InvalidArgumentException('Event date/time is required.');
        }
        if (strtotime($data['event_datetime']) === false) {
            throw new InvalidArgumentException('Event date/time is not a valid date string.');
        }
    }
}
