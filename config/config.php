<?php

declare(strict_types=1);

// Load .env if present (local dev). On Railway, env vars are injected directly — no .env file exists.
$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..');
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
        'api_url'                   => getenv('DAKTELA_API_URL'),
        'access_token'              => getenv('DAKTELA_ACCESS_TOKEN'),
        // Endpoint paths default to Daktela's standard names if not overridden in .env
        'contacts_endpoint'         => getenv('DAKTELA_CONTACTS_ENDPOINT')         ?: 'CrmContacts',
        'contact_statuses_endpoint' => getenv('DAKTELA_CONTACT_STATUSES_ENDPOINT') ?: 'CrmStatuses',
        'tickets_endpoint'          => getenv('DAKTELA_TICKETS_ENDPOINT')           ?: 'Tickets',
        'ticket_statuses_endpoint'  => getenv('DAKTELA_TICKET_STATUSES_ENDPOINT')  ?: 'TicketStatuses',
        // filter_var is required here — (bool)"false" === true in PHP, so a plain cast would silently ignore .env
        'verify_ssl'                => filter_var(getenv('GUZZLE_VERIFY_SSL') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    ],
    'db' => [
        'host'     => getenv('DB_HOST'),
        // env vars are always strings — cast to int so callers get a typed value
        'port'     => (int) (getenv('DB_PORT') ?: 3306),
        'name'     => getenv('DB_NAME'),
        'user'     => getenv('DB_USER'),
        'password' => getenv('DB_PASSWORD'),
        'charset'  => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
    'log' => [
        'path'  => getenv('LOG_PATH')  ?: 'logs/app.log',
        'level' => getenv('LOG_LEVEL') ?: 'info',
    ],
    'sync' => [
        // How often the daemon wakes up to pull fresh data from Daktela (seconds)
        'interval' => (int) (getenv('SYNC_INTERVAL') ?: 3600),
    ],
    'app' => [
        'url' => getenv('APP_URL') ?: '',
        'env' => getenv('APP_ENV') ?: 'production',
    ],
];