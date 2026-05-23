<?php

declare(strict_types=1);

namespace App\Infrastructure\External;

// HTTP client for the Daktela REST API.
// Wraps Guzzle — all knowledge of Daktela's URL structure lives here.
// SyncService calls this; it knows nothing about HTTP.
class DaktelaApiClient
{
}
