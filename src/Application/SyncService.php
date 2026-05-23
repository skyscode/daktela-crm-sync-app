<?php

declare(strict_types=1);

namespace App\Application;

// Doing one full sync cycle: fetch from Daktela API → upsert into DB.
// Called by daemon.php on every interval tick.
// Depends on DaktelaApiClient and the three repositories (injected via constructor).
// Logs cycle start/end, counts inserted/updated/skipped per entity, and any errors.
class SyncService
{
}
