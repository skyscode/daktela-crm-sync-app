<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Contact;
use App\Infrastructure\Database;

class ContactRepository
{
    private \PDO $pdo;

    public function __construct(array $config = [])
    {
        $this->pdo = Database::connection($config);
    }

    public function beginTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function upsert(Contact $contact): void
    {
        $sql = "
            INSERT INTO contacts (external_id, title, description, status_id, created_at, updated_at, synced_at)
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
            'external_id' => $contact->externalId,
            'title'       => $contact->title,
            'description' => $contact->description,
            'status_id'   => $contact->statusId,
            'created_at'  => $contact->createdAt,
            'updated_at'  => $contact->updatedAt,
            'synced_at'   => $contact->syncedAt,
        ]);
    }

    public function findByExternalId(string $externalId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   s.title AS status_title
            FROM contacts c
            LEFT JOIN statuses s ON s.id = c.status_id
            WHERE c.external_id = :external_id
        ");
        $stmt->execute(['external_id' => $externalId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? $this->formatRow($row) : null;
    }

    public function count(?int $statusId = null): int
    {
        $where = $statusId !== null ? "WHERE status_id = :status_id" : "";
        $stmt  = $this->pdo->prepare("SELECT COUNT(*) FROM contacts {$where}");
        if ($statusId !== null) {
            $stmt->bindValue('status_id', $statusId, \PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function findAll(int $page = 1, int $limit = 20, ?int $statusId = null): array
    {
        $offset = ($page - 1) * $limit;
        $where  = $statusId !== null ? "WHERE c.status_id = :status_id" : "";
        $sql    = "
            SELECT c.*,
                   s.title AS status_title
            FROM contacts c
            LEFT JOIN statuses s ON s.id = c.status_id
            {$where}
            ORDER BY c.id ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        if ($statusId !== null) {
            $stmt->bindValue('status_id', $statusId, \PDO::PARAM_INT);
        }
        $stmt->bindValue('limit',  $limit,  \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            fn(array $row) => $this->formatRow($row),
            $stmt->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    private function formatRow(array $row): array
    {
        return [
            'external_id' => $row['external_id'],
            'title'       => $row['title'],
            'description' => $row['description'],
            'status'      => isset($row['status_id']) ? [
                'id'    => (int) $row['status_id'],
                'title' => $row['status_title'] ?? null,
            ] : null,
            'created_at'  => $row['created_at'],
            'updated_at'  => $row['updated_at'],
            'synced_at'   => $row['synced_at'],
        ];
    }
}
