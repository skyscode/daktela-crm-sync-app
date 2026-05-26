<?php

declare(strict_types=1);

// API entry point — bootstraps the app and dispatches every HTTP request.
// Apache/.htaccess routes all /api/* requests here.

require_once __DIR__ . '/../../vendor/autoload.php';

$config = require __DIR__ . '/../../config/config.php';

use App\Infrastructure\Router;
use App\Infrastructure\Persistence\ContactRepository;
use App\Infrastructure\Persistence\TicketRepository;
use App\Infrastructure\Persistence\StatusRepository;
use App\Presentation\Controllers\ContactController;
use App\Presentation\Controllers\TicketController;
use App\Presentation\Controllers\StatusController;
// SyncService and its dependencies — used by the /api/sync endpoint to trigger a manual sync
use App\Application\SyncService;
use App\Infrastructure\External\DaktelaApiClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$pdo = App\Infrastructure\Database::connection($config['db']);
(new App\Infrastructure\Migrator($pdo))->run();

$contactController = new ContactController(new ContactRepository($config['db']));
$ticketController  = new TicketController(new TicketRepository($config['db']));
$statusController  = new StatusController(new StatusRepository($config['db']));

$router = new Router();

$router->add('GET', '/api/contacts',       [$contactController, 'index']);
$router->add('GET', '/api/contacts/{id}',  [$contactController, 'show']);
$router->add('GET', '/api/tickets',        [$ticketController,  'index']);
$router->add('GET', '/api/tickets/{id}',   [$ticketController,  'show']);
$router->add('GET', '/api/statuses',       [$statusController,  'index']);

// Debug endpoint — returns raw Daktela API response to verify connectivity and response format
$router->add('GET', '/api/debug', function () use ($config) {
    $client = new \GuzzleHttp\Client([
        'base_uri' => rtrim($config['daktela']['api_url'], '/') . '/',
        'verify'   => $config['daktela']['verify_ssl'],
        'timeout'  => 30,
    ]);

    $endpoint = $_GET['endpoint'] ?? 'contacts.json';
    $extra    = array_diff_key($_GET, array_flip(['endpoint']));
    try {
        $response = $client->get($endpoint, [
            'query' => array_merge(['accessToken' => $config['daktela']['access_token'], 'take' => 1, 'skip' => 0], $extra),
        ]);
        $body = json_decode($response->getBody()->getContents(), true);
    } catch (\Throwable $e) {
        $body = ['error' => $e->getMessage()];
    }

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode($body);
});

// Manual sync trigger — hits Daktela API and upserts all entities into the DB immediately
$router->add('POST', '/api/sync', function () use ($config) {
    $logger = new Logger('sync');
    $logger->pushHandler(new StreamHandler('php://stdout', \Monolog\Level::Info));

    $sync = new SyncService(
        new DaktelaApiClient($config['daktela']),
        new ContactRepository($config['db']),
        new TicketRepository($config['db']),
        new StatusRepository($config['db']),
        $logger,
    );

    try {
        $results = $sync->run();
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Sync completed', 'results' => $results]);
    } catch (\Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    }
});

$router->dispatch();