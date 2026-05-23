<?php

declare(strict_types=1);

namespace App\Domain;

// Represents a Status as a plain PHP object.
// type enum: 'contact' | 'ticket' — determines which entity this status applies to.
// Fields: id, external_id, title, description, type, created_at, updated_at, synced_at
class Status
{
}
