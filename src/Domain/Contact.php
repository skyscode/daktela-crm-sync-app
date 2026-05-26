<?php

declare(strict_types=1);

namespace App\Domain;

class Contact implements \JsonSerializable
{
    public function __construct(
        public readonly ?int    $id,
        public readonly string  $externalId,
        public readonly string  $title,
        public readonly ?string $description,
        public readonly ?int    $statusId,
        public readonly string  $createdAt,
        public readonly string  $updatedAt,
        public readonly string  $syncedAt,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'id'          => $this->id,
            'external_id' => $this->externalId,
            'title'       => $this->title,
            'description' => $this->description,
            'status_id'   => $this->statusId,
            'created_at'  => $this->createdAt,
            'updated_at'  => $this->updatedAt,
            'synced_at'   => $this->syncedAt,
        ];
    }

    public static function fromApiResponse(array $data, string $syncedAt, ?int $statusId = null): self
    {
        return new self(
            id:          null,
            externalId:  (string) $data['name'],
            title:       (string) $data['title'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            statusId:    $statusId,
            createdAt:   (string) $data['created'],
            updatedAt:   (string) $data['edited'],
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
            statusId:    isset($data['status_id']) ? (int) $data['status_id'] : null,
            createdAt:   (string) $data['created_at'],
            updatedAt:   (string) $data['updated_at'],
            syncedAt:    (string) $data['synced_at'],
        );
    }
}
