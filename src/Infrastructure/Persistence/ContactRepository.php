<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;
use App\Domain\Contact;
use App\Infrastructure\Database;

// MySQL implementation of contact persistence.
// All queries against the contacts table live here — no SQL anywhere else.

class ContactRepository
{
    private \PDO $pdo;

    public function __construct(array $config = [])
    {
        $this->pdo = Database::connection($config);
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

    public function findById(int $id): ?Contact
    {
        $stmt = $this->pdo->prepare("SELECT * FROM contacts WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? Contact::fromDbRow($row) : null;
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

    public function findByExternalId(string $externalId): ?Contact
    {
        $stmt = $this->pdo->prepare("SELECT * FROM contacts WHERE external_id = :external_id");
        $stmt->execute(['external_id' => $externalId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? Contact::fromDbRow($row) : null;
    }

    public function findAll(int $page = 1, int $limit = 20, ?int $statusId = null): array
    {
        $offset = ($page - 1) * $limit;
        $where  = $statusId !== null ? "WHERE status_id = :status_id" : "";
        $sql    = "SELECT * FROM contacts {$where} LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        if ($statusId !== null) {
            $stmt->bindValue('status_id', $statusId, \PDO::PARAM_INT);
        }
        $stmt->bindValue('limit',  $limit,  \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            fn(array $row) => Contact::fromDbRow($row),
            $stmt->fetchAll(\PDO::FETCH_ASSOC)
        );
    }
}