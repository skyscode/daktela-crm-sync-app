<?php

declare(strict_types=1);

namespace App\Domain;

// Represents a CRM Contact as a plain PHP object (no DB logic here).
// Constructed from raw data (array from DB or API) via a static factory method.
// Fields: id, external_id, title, description, status_id, created_at, updated_at, synced_at
class Contact
{
}
