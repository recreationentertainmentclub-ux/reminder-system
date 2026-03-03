<?php

declare(strict_types=1);

namespace ReminderSystem\Tests;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use ReminderSystem\Reminder;

/**
 * Unit tests for the Reminder model.
 *
 * Uses an in-memory SQLite database so no real MySQL connection is needed.
 */
class ReminderTest extends TestCase
{
    private PDO $db;
    private Reminder $model;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Create a minimal table that mirrors the MySQL schema
        $this->db->exec(
            'CREATE TABLE reminders (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                title          TEXT    NOT NULL,
                description    TEXT,
                email          TEXT    NOT NULL,
                phone          TEXT,
                event_datetime TEXT    NOT NULL,
                remind_24_sent INTEGER NOT NULL DEFAULT 0,
                remind_12_sent INTEGER NOT NULL DEFAULT 0,
                status         TEXT    NOT NULL DEFAULT \'active\',
                created_at     TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at     TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $this->model = new Reminder($this->db);
    }

    // ------------------------------------------------------------------
    // create
    // ------------------------------------------------------------------

    public function testCreateReturnsNewId(): void
    {
        $id = $this->model->create([
            'title'          => 'Test Event',
            'email'          => 'alice@example.com',
            'event_datetime' => '2099-12-31 10:00:00',
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateThrowsOnMissingTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->model->create([
            'title'          => '',
            'email'          => 'alice@example.com',
            'event_datetime' => '2099-12-31 10:00:00',
        ]);
    }

    public function testCreateThrowsOnInvalidEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->model->create([
            'title'          => 'Test',
            'email'          => 'not-an-email',
            'event_datetime' => '2099-12-31 10:00:00',
        ]);
    }

    public function testCreateThrowsOnMissingDatetime(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->model->create([
            'title'          => 'Test',
            'email'          => 'alice@example.com',
            'event_datetime' => '',
        ]);
    }

    // ------------------------------------------------------------------
    // getById
    // ------------------------------------------------------------------

    public function testGetByIdReturnsCorrectRow(): void
    {
        $id       = $this->model->create([
            'title'          => 'My Reminder',
            'email'          => 'bob@example.com',
            'event_datetime' => '2099-06-15 09:00:00',
        ]);
        $reminder = $this->model->getById($id);

        $this->assertNotNull($reminder);
        $this->assertSame('My Reminder', $reminder['title']);
        $this->assertSame('bob@example.com', $reminder['email']);
    }

    public function testGetByIdReturnsNullForMissingId(): void
    {
        $this->assertNull($this->model->getById(999999));
    }

    // ------------------------------------------------------------------
    // update
    // ------------------------------------------------------------------

    public function testUpdateChangesTitle(): void
    {
        $id = $this->model->create([
            'title'          => 'Original Title',
            'email'          => 'carol@example.com',
            'event_datetime' => '2099-01-01 12:00:00',
        ]);

        $this->model->update($id, [
            'title'          => 'Updated Title',
            'email'          => 'carol@example.com',
            'event_datetime' => '2099-01-01 12:00:00',
        ]);

        $reminder = $this->model->getById($id);
        $this->assertSame('Updated Title', $reminder['title']);
    }

    // ------------------------------------------------------------------
    // cancel / delete
    // ------------------------------------------------------------------

    public function testCancelSetsStatusToCancelled(): void
    {
        $id = $this->model->create([
            'title'          => 'To Cancel',
            'email'          => 'dave@example.com',
            'event_datetime' => '2099-03-10 08:00:00',
        ]);

        $this->model->cancel($id);

        $reminder = $this->model->getById($id);
        $this->assertSame('cancelled', $reminder['status']);
    }

    public function testDeleteRemovesRow(): void
    {
        $id = $this->model->create([
            'title'          => 'To Delete',
            'email'          => 'eve@example.com',
            'event_datetime' => '2099-04-20 14:00:00',
        ]);

        $this->model->delete($id);

        $this->assertNull($this->model->getById($id));
    }

    // ------------------------------------------------------------------
    // mark sent
    // ------------------------------------------------------------------

    public function testMarkRemind24SentFlipsFlag(): void
    {
        $id = $this->model->create([
            'title'          => 'Flag Test',
            'email'          => 'frank@example.com',
            'event_datetime' => '2099-07-04 10:00:00',
        ]);

        $this->model->markRemind24Sent($id);

        $reminder = $this->model->getById($id);
        $this->assertSame('1', (string)$reminder['remind_24_sent']);
    }

    public function testMarkRemind12SentFlipsFlag(): void
    {
        $id = $this->model->create([
            'title'          => 'Flag Test 12',
            'email'          => 'grace@example.com',
            'event_datetime' => '2099-08-08 10:00:00',
        ]);

        $this->model->markRemind12Sent($id);

        $reminder = $this->model->getById($id);
        $this->assertSame('1', (string)$reminder['remind_12_sent']);
    }

    // ------------------------------------------------------------------
    // getAll
    // ------------------------------------------------------------------

    public function testGetAllReturnsAllRows(): void
    {
        $this->model->create([
            'title' => 'A', 'email' => 'a@a.com', 'event_datetime' => '2099-01-01 00:00:00',
        ]);
        $this->model->create([
            'title' => 'B', 'email' => 'b@b.com', 'event_datetime' => '2099-02-01 00:00:00',
        ]);

        $all = $this->model->getAll();
        $this->assertCount(2, $all);
    }
}
