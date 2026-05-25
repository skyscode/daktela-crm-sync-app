<?php

declare(strict_types=1);

// Long-running sync daemon — run with: php daemon.php
// Loops forever, syncing from Daktela API every $interval seconds.
// Handles SIGTERM/SIGINT for clean shutdown and runs GC after each cycle.

require_once __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config/config.php';

use App\Application\SyncService;
use App\Infrastructure\External\DaktelaApiClient;
use App\Infrastructure\Persistence\ContactRepository;
use App\Infrastructure\Persistence\TicketRepository;
use App\Infrastructure\Persistence\StatusRepository;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('daemon');
$logger->pushHandler(new StreamHandler($config['log']['path'], \Monolog\Level::Info));
$logger->pushHandler(new StreamHandler('php://stdout', \Monolog\Level::Info));

$syncService = new SyncService(
    new DaktelaApiClient($config['daktela']),
    new ContactRepository(),
    new TicketRepository(),
    new StatusRepository(),
    $logger,
);

$interval = $config['sync']['interval'];
$running  = true;

pcntl_signal(SIGTERM, function () use (&$running) { $running = false; });
pcntl_signal(SIGINT,  function () use (&$running) { $running = false; });

$logger->info('Daemon started', ['interval' => $interval]);

while ($running) {
    try {
        $syncService->run();
    } catch (\Throwable $e) {
        $logger->error('Unhandled error in sync cycle', ['error' => $e->getMessage()]);
    }

    gc_collect_cycles();

    $logger->info("Sleeping for {$interval} seconds");

    $slept = 0;
    while ($running && $slept < $interval) {
        pcntl_signal_dispatch();
        sleep(1);
        $slept++;
    }
}

$logger->info('Daemon stopped');