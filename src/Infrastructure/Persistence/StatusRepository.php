<?php

// MySQL implementation of status persistence.
// All queries against the statuses table live here.

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Status;
use App\Infrastructure\Database;

class StatusRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function upsert(Status $status): void
    {
        $sql = "
            INSERT INTO statuses (external_id, title, description, type, created_at, updated_at, synced_at)
            VALUES (:external_id, :title, :description, :type, :created_at, :updated_at, :synced_at)
            ON DUPLICATE KEY UPDATE
                title       = VALUES(title),
                description = VALUES(description),
                type        = VALUES(type),
                updated_at  = VALUES(updated_at),
                synced_at   = VALUES(synced_at)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'external_id' => $status->externalId,
            'title'       => $status->title,
            'description' => $status->description,
            'type'        => $status->type,
            'created_at'  => $status->createdAt,
            'updated_at'  => $status->updatedAt,
            'synced_at'   => $status->syncedAt,
        ]);
    }

    public function findByExternalId(string $externalId): ?Status
    {
        $stmt = $this->pdo->prepare("SELECT * FROM statuses WHERE external_id = :external_id");
        $stmt->execute(['external_id' => $externalId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? Status::fromArray($row) : null;
    }

    public function findAll(int $page = 1, int $limit = 20, ?string $type = null): array
    {
        $offset = ($page - 1) * $limit;
        $where  = $type !== null ? "WHERE type = :type" : "";
        $sql    = "SELECT * FROM statuses {$where} LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        if ($type !== null) {
            $stmt->bindValue('type', $type, \PDO::PARAM_STR);
        }
        $stmt->bindValue('limit',  $limit,  \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            fn(array $row) => Status::fromArray($row),
            $stmt->fetchAll(\PDO::FETCH_ASSOC)
        );
    }
}
