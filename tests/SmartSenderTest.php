<?php

declare(strict_types=1);

namespace ReminderSystem\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use ReminderSystem\SmartSender;

/**
 * Unit tests for SmartSender.
 *
 * The actual HTTP call is NOT made; we verify the logging behaviour by
 * inspecting the reminder_logs table after a call that is expected to fail
 * (no real API key / URL).
 */
class SmartSenderTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->db->exec(
            'CREATE TABLE reminder_logs (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                reminder_id INTEGER NOT NULL,
                tag         TEXT    NOT NULL,
                success     INTEGER NOT NULL DEFAULT 0,
                response    TEXT,
                sent_at     TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }

    public function testConstantsAreCorrect(): void
    {
        $this->assertSame('REMIND_24', SmartSender::TAG_24H);
        $this->assertSame('REMIND_12', SmartSender::TAG_12H);
    }

    public function testFailedSendIsLoggedAsFailure(): void
    {
        // Use a deliberately invalid URL so the HTTP request fails immediately.
        $sender = new SmartSender(
            'http://127.0.0.1:0',  // nothing listening here
            'fake-key',
            $this->db
        );

        $reminder = [
            'id'             => 42,
            'title'          => 'Test Event',
            'description'    => 'Some desc',
            'email'          => 'user@example.com',
            'phone'          => null,
            'event_datetime' => '2099-01-01 10:00:00',
        ];

        $result = $sender->sendTag($reminder, SmartSender::TAG_24H);

        $this->assertFalse($result);

        $row = $this->db
            ->query('SELECT * FROM reminder_logs WHERE reminder_id = 42')
            ->fetch();

        $this->assertNotFalse($row);
        $this->assertSame('REMIND_24', $row['tag']);
        $this->assertSame('0', (string)$row['success']);
    }
}
