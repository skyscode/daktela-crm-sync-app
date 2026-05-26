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

// Run schema migrations on every boot
$pdo = App\Infrastructure\Database::connection($config['db']);
$pdo->exec(file_get_contents(__DIR__ . '/../../database/scheme.sql'));

// Add type column to statuses if it doesn't exist (for existing deployments)
try { $pdo->exec("ALTER TABLE statuses ADD COLUMN type ENUM('contact','ticket') NULL"); } catch (\PDOException $e) {}
try { $pdo->exec("ALTER TABLE statuses ADD INDEX idx_statuses_type (type)"); } catch (\PDOException $e) {}

// Triggers enforce the status type business rule at the DB level (DROP + CREATE = idempotent)
foreach (['trg_contacts_status_type_insert','trg_contacts_status_type_update','trg_tickets_status_type_insert','trg_tickets_status_type_update'] as $t) {
    $pdo->exec("DROP TRIGGER IF EXISTS {$t}");
}
$pdo->exec("CREATE TRIGGER trg_contacts_status_type_insert BEFORE INSERT ON contacts FOR EACH ROW BEGIN DECLARE v_type VARCHAR(20); IF NEW.status_id IS NOT NULL THEN SELECT type INTO v_type FROM statuses WHERE id = NEW.status_id; IF v_type IS NULL OR v_type != 'contact' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Contact status_id must reference a status of type=contact'; END IF; END IF; END");
$pdo->exec("CREATE TRIGGER trg_contacts_status_type_update BEFORE UPDATE ON contacts FOR EACH ROW BEGIN DECLARE v_type VARCHAR(20); IF NEW.status_id IS NOT NULL THEN SELECT type INTO v_type FROM statuses WHERE id = NEW.status_id; IF v_type IS NULL OR v_type != 'contact' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Contact status_id must reference a status of type=contact'; END IF; END IF; END");
$pdo->exec("CREATE TRIGGER trg_tickets_status_type_insert BEFORE INSERT ON tickets FOR EACH ROW BEGIN DECLARE v_type VARCHAR(20); IF NEW.status_id IS NOT NULL THEN SELECT type INTO v_type FROM statuses WHERE id = NEW.status_id; IF v_type IS NULL OR v_type != 'ticket' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ticket status_id must reference a status of type=ticket'; END IF; END IF; END");
$pdo->exec("CREATE TRIGGER trg_tickets_status_type_update BEFORE UPDATE ON tickets FOR EACH ROW BEGIN DECLARE v_type VARCHAR(20); IF NEW.status_id IS NOT NULL THEN SELECT type INTO v_type FROM statuses WHERE id = NEW.status_id; IF v_type IS NULL OR v_type != 'ticket' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ticket status_id must reference a status of type=ticket'; END IF; END IF; END");

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
    try {
        $response = $client->get($endpoint, [
            'query' => ['accessToken' => $config['daktela']['access_token'], 'take' => 1, 'skip' => 0],
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

    $sync->run();

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Sync completed']);
});

$router->dispatch();