<?php

declare(strict_types=1);

/**
 * tests/ApiTest.php — Repository read tests against in-memory SQLite.
 *
 * Tests findAll, findById, and count for Contact, Ticket, and Status.
 * Uses SQLite so no MySQL setup is required. Upsert is not tested here
 * because ON DUPLICATE KEY UPDATE is MySQL-specific syntax.
 */

use App\Infrastructure\Database;
use App\Infrastructure\Persistence\ContactRepository;
use App\Infrastructure\Persistence\TicketRepository;
use App\Infrastructure\Persistence\StatusRepository;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        $this->pdo->exec("
            CREATE TABLE statuses (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                external_id TEXT NOT NULL UNIQUE,
                title       TEXT NOT NULL,
                description TEXT,
                created_at  TEXT NOT NULL,
                updated_at  TEXT NOT NULL,
                synced_at   TEXT NOT NULL
            )
        ");

        $this->pdo->exec("
            CREATE TABLE contacts (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                external_id TEXT NOT NULL UNIQUE,
                title       TEXT NOT NULL,
                description TEXT,
                status_id   INTEGER,
                created_at  TEXT NOT NULL,
                updated_at  TEXT NOT NULL,
                synced_at   TEXT NOT NULL
            )
        ");

        $this->pdo->exec("
            CREATE TABLE tickets (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                external_id TEXT NOT NULL UNIQUE,
                title       TEXT NOT NULL,
                description TEXT,
                status_id   INTEGER,
                created_at  TEXT NOT NULL,
                updated_at  TEXT NOT NULL,
                synced_at   TEXT NOT NULL
            )
        ");

        Database::setInstance($this->pdo);
    }

    private function insertContact(string $externalId, string $title): void
    {
        $this->pdo->prepare("
            INSERT INTO contacts (external_id, title, description, status_id, created_at, updated_at, synced_at)
            VALUES (:external_id, :title, NULL, NULL, '2024-01-01 00:00:00', '2024-01-01 00:00:00', '2024-01-01 00:00:00')
        ")->execute(['external_id' => $externalId, 'title' => $title]);
    }

    private function insertTicket(string $externalId, string $title): void
    {
        $this->pdo->prepare("
            INSERT INTO tickets (external_id, title, description, status_id, created_at, updated_at, synced_at)
            VALUES (:external_id, :title, NULL, NULL, '2024-01-01 00:00:00', '2024-01-01 00:00:00', '2024-01-01 00:00:00')
        ")->execute(['external_id' => $externalId, 'title' => $title]);
    }

    private function insertStatus(string $externalId, string $title): void
    {
        $this->pdo->prepare("
            INSERT INTO statuses (external_id, title, description, created_at, updated_at, synced_at)
            VALUES (:external_id, :title, NULL, '2024-01-01 00:00:00', '2024-01-01 00:00:00', '2024-01-01 00:00:00')
        ")->execute(['external_id' => $externalId, 'title' => $title]);
    }

    // --- ContactRepository ---

    public function testContactFindAllReturnsPaginatedResults(): void
    {
        $this->insertContact('c1', 'Alice');
        $this->insertContact('c2', 'Bob');
        $this->insertContact('c3', 'Carol');

        $repo    = new ContactRepository();
        $results = $repo->findAll(page: 1, limit: 2);

        $this->assertCount(2, $results);
        $this->assertSame('Alice', $results[0]->title);
    }

    public function testContactFindByIdReturnsContact(): void
    {
        $this->insertContact('c1', 'Alice');

        $repo    = new ContactRepository();
        $contact = $repo->findById(1);

        $this->assertNotNull($contact);
        $this->assertSame('Alice', $contact->title);
    }

    public function testContactFindByIdReturnsNullForUnknownId(): void
    {
        $repo = new ContactRepository();

        $this->assertNull($repo->findById(999));
    }

    public function testContactCountReturnsTotal(): void
    {
        $this->insertContact('c1', 'Alice');
        $this->insertContact('c2', 'Bob');

        $repo = new ContactRepository();

        $this->assertSame(2, $repo->count());
    }

    // --- TicketRepository ---

    public function testTicketFindAllReturnsPaginatedResults(): void
    {
        $this->insertTicket('t1', 'Issue A');
        $this->insertTicket('t2', 'Issue B');

        $repo    = new TicketRepository();
        $results = $repo->findAll(page: 1, limit: 10);

        $this->assertCount(2, $results);
    }

    public function testTicketFindByIdReturnsTicket(): void
    {
        $this->insertTicket('t1', 'Issue A');

        $repo   = new TicketRepository();
        $ticket = $repo->findById(1);

        $this->assertNotNull($ticket);
        $this->assertSame('Issue A', $ticket->title);
    }

    public function testTicketFindByIdReturnsNullForUnknownId(): void
    {
        $repo = new TicketRepository();

        $this->assertNull($repo->findById(999));
    }

    public function testTicketCountReturnsTotal(): void
    {
        $this->insertTicket('t1', 'Issue A');

        $repo = new TicketRepository();

        $this->assertSame(1, $repo->count());
    }

    // --- StatusRepository ---

    public function testStatusFindAllReturnsPaginatedResults(): void
    {
        $this->insertStatus('s1', 'Open');
        $this->insertStatus('s2', 'Closed');

        $repo    = new StatusRepository();
        $results = $repo->findAll(page: 1, limit: 10);

        $this->assertCount(2, $results);
    }

    public function testStatusCountReturnsTotal(): void
    {
        $this->insertStatus('s1', 'Open');
        $this->insertStatus('s2', 'Closed');

        $repo = new StatusRepository();

        $this->assertSame(2, $repo->count());
    }
}
