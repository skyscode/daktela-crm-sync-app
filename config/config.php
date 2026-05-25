<?php

declare(strict_types=1);

// Load .env if present (local dev). On Railway, env vars are injected directly — no .env file exists.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// These keys must exist either in .env or as real environment variables — fail fast if missing.
$dotenv->required([
    'DAKTELA_API_URL',
    'DAKTELA_ACCESS_TOKEN',
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_PASSWORD',
]);

return [
    'daktela' => [
        'api_url'                   => $_ENV['DAKTELA_API_URL'],
        'access_token'              => $_ENV['DAKTELA_ACCESS_TOKEN'],
        // Endpoint paths default to Daktela's standard names if not overridden in .env
        'contacts_endpoint'         => $_ENV['DAKTELA_CONTACTS_ENDPOINT']         ?? 'CrmContacts',
        'contact_statuses_endpoint' => $_ENV['DAKTELA_CONTACT_STATUSES_ENDPOINT'] ?? 'CrmStatuses',
        'tickets_endpoint'          => $_ENV['DAKTELA_TICKETS_ENDPOINT']           ?? 'Tickets',
        'ticket_statuses_endpoint'  => $_ENV['DAKTELA_TICKET_STATUSES_ENDPOINT']  ?? 'TicketStatuses',
        // filter_var is required here — (bool)"false" === true in PHP, so a plain cast would silently ignore .env
        'verify_ssl'                => filter_var($_ENV['GUZZLE_VERIFY_SSL'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
    ],
    'db' => [
        'host'     => $_ENV['DB_HOST'],
        // env vars are always strings — cast to int so callers get a typed value
        'port'     => (int) ($_ENV['DB_PORT'] ?? 3306),
        'name'     => $_ENV['DB_NAME'],
        'user'     => $_ENV['DB_USER'],
        'password' => $_ENV['DB_PASSWORD'],
        'charset'  => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    ],
    'log' => [
        'path'  => $_ENV['LOG_PATH']  ?? 'logs/app.log',
        'level' => $_ENV['LOG_LEVEL'] ?? 'info',
    ],
    'sync' => [
        // How often the daemon wakes up to pull fresh data from Daktela (seconds)
        'interval' => (int) ($_ENV['SYNC_INTERVAL'] ?? 3600),
    ],
    'app' => [
        'url' => $_ENV['APP_URL'] ?? '',
        'env' => $_ENV['APP_ENV'] ?? 'production',
    ],
];