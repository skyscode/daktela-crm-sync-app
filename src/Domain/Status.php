<?php

declare(strict_types=1);

namespace App\Domain;

// Represents a Status as a plain PHP object.
// Fields: id, external_id, title, description, created_at, updated_at, synced_at
class Status
{
    public function __construct(
        public readonly ?int    $id,
        public readonly string  $externalId,
        public readonly string  $title,
        public readonly ?string $description,
        public readonly string  $createdAt,
        public readonly string  $updatedAt,
        public readonly string  $syncedAt,
    ) {}

    public static function fromApiResponse(array $data, string $syncedAt): self
    {
        return new self(
            id:          null,
            externalId:  (string) $data['name'],
            title:       (string) $data['title'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            createdAt:   $syncedAt,
            updatedAt:   $syncedAt,
            syncedAt:    $syncedAt,
        );
    }

    public static function fromDbRow(array $data): self
    {
        return new self(
            id:          (int) $data['id'],
            externalId:  (string) $data['external_id'],
            title:       (string) $data['title'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            createdAt:   (string) $data['created_at'],
            updatedAt:   (string) $data['updated_at'],
            syncedAt:    (string) $data['synced_at'],
        );
    }
}
