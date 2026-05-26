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

// Run schema migrations on every boot — safe because all statements use IF NOT EXISTS
$pdo = App\Infrastructure\Database::connection($config['db']);
$pdo->exec(file_get_contents(__DIR__ . '/../../database/scheme.sql'));

$contactController = new ContactController(new ContactRepository($config['db']));
$ticketController  = new TicketController(new TicketRepository($config['db']));
$statusController  = new StatusController(new StatusRepository($config['db']));

$router = new Router();

$router->add('GET', '/api/contacts',       [$contactController, 'index']);
$router->add('GET', '/api/contacts/{id}',  [$contactController, 'show']);
$router->add('GET', '/api/tickets',        [$ticketController,  'index']);
$router->add('GET', '/api/tickets/{id}',   [$ticketController,  'show']);
$router->add('GET', '/api/statuses',       [$statusController,  'index']);

$router->dispatch();