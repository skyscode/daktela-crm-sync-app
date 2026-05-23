<?php

declare(strict_types=1);

namespace App\Domain;

// Represents a Ticket as a plain PHP object (no DB logic here).
// Same shape as Contact but belongs to the ticket status type.
// Fields: id, external_id, title, description, status_id, created_at, updated_at, synced_at
class Ticket
{
}
