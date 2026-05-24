<?php

declare(strict_types=1);

namespace App\Domain;

// Represents a Ticket as a plain PHP object
// Fields: id, external_id, title, description, status_id, created_at, updated_at, synced_at
 
class Ticket
{
      public function __construct(
        public readonly ?int    $id,
        public readonly string  $externalId,
        public readonly string  $title,
        public readonly ?string $description,
        public readonly int     $statusId,
        public readonly string  $createdAt,
        public readonly string  $updatedAt,
        public readonly string  $syncedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:          isset($data['id']) ? (int) $data['id'] : null,
            externalId:  (string) $data['external_id'],
            title:       (string) $data['title'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            statusId:    (int) $data['status_id'],
            createdAt:   (string) $data['created_at'],
            updatedAt:   (string) $data['updated_at'],
            syncedAt:    (string) $data['synced_at'],
        );
    }
}
