<?php

declare(strict_types=1);

namespace App\Domain;

class Status implements \JsonSerializable
{
    public function __construct(
        public readonly ?int    $id,
        public readonly string  $externalId,
        public readonly string  $title,
        public readonly ?string $description,
        public readonly ?string $type,
        public readonly string  $createdAt,
        public readonly string  $updatedAt,
        public readonly string  $syncedAt,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'external_id' => $this->externalId,
            'title'       => $this->title,
            'description' => $this->description,
            'type'        => $this->type,
            'created_at'  => $this->createdAt,
            'updated_at'  => $this->updatedAt,
            'synced_at'   => $this->syncedAt,
        ];
    }

    public static function fromApiResponse(array $data, string $syncedAt, string $type = 'contact'): self
    {
        return new self(
            id:          null,
            externalId:  (string) $data['name'],
            title:       (string) ($data['title'] ?? $data['name']),
            description: isset($data['description']) ? (string) $data['description'] : null,
            type:        $type,
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
            type:        isset($data['type']) ? (string) $data['type'] : null,
            createdAt:   (string) $data['created_at'],
            updatedAt:   (string) $data['updated_at'],
            syncedAt:    (string) $data['synced_at'],
        );
    }
}
