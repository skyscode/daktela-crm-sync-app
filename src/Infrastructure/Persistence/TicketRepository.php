<?php

// MySQL implementation of ticket persistence
// All queries against the tickets table live here

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Ticket;
use App\Infrastructure\Database;

class TicketRepository
{
    private \PDO $pdo;

    public function __construct(array $config = [])
    {
        $this->pdo = Database::connection($config);
    }

    public function upsert(Ticket $ticket): void
    {
        $sql = "
            INSERT INTO tickets (external_id, title, description, status_id, created_at, updated_at, synced_at)
            VALUES (:external_id, :title, :description, :status_id, :created_at, :updated_at, :synced_at)
            ON DUPLICATE KEY UPDATE
                title       = VALUES(title),
                description = VALUES(description),
                status_id   = VALUES(status_id),
                updated_at  = VALUES(updated_at),
                synced_at   = VALUES(synced_at)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'external_id' => $ticket->externalId,
            'title'       => $ticket->title,
            'description' => $ticket->description,
            'status_id'   => $ticket->statusId,
            'created_at'  => $ticket->createdAt,
            'updated_at'  => $ticket->updatedAt,
            'synced_at'   => $ticket->syncedAt,
        ]);
    }

    public function findById(int $id): ?Ticket
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tickets WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? Ticket::fromDbRow($row) : null;
    }

    public function count(?int $statusId = null): int
    {
        $where = $statusId !== null ? "WHERE status_id = :status_id" : "";
        $stmt  = $this->pdo->prepare("SELECT COUNT(*) FROM tickets {$where}");
        if ($statusId !== null) {
            $stmt->bindValue('status_id', $statusId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function findByExternalId(string $externalId): ?Ticket
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tickets WHERE external_id = :external_id");
        $stmt->execute(['external_id' => $externalId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? Ticket::fromDbRow($row) : null;
    }

    public function findAll(int $page = 1, int $limit = 20, ?int $statusId = null): array
    {
        $offset = ($page - 1) * $limit;
        $where  = $statusId !== null ? "WHERE status_id = :status_id" : "";
        $sql    = "SELECT * FROM tickets {$where} LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        if ($statusId !== null) {
            $stmt->bindValue('status_id', $statusId, \PDO::PARAM_INT);
        }
        $stmt->bindValue('limit',  $limit,  \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            fn(array $row) => Ticket::fromDbRow($row),
            $stmt->fetchAll(\PDO::FETCH_ASSOC)
        );
    }
}
