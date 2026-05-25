<?php

declare(strict_types=1);

/**
 * tests/SyncServiceTest.php — SyncService orchestration tests.
 *
 * Verifies that SyncService correctly calls the API client and repositories
 * during a sync cycle. All dependencies are mocked — no real DB or API needed.
 */

use App\Application\SyncService;
use App\Infrastructure\External\DaktelaApiClient;
use App\Infrastructure\Persistence\ContactRepository;
use App\Infrastructure\Persistence\TicketRepository;
use App\Infrastructure\Persistence\StatusRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SyncServiceTest extends TestCase
{
    public function testRunFetchesAndUpsertAllEntities(): void
    {
        $apiClient = $this->createMock(DaktelaApiClient::class);
        $contacts  = $this->createMock(ContactRepository::class);
        $tickets   = $this->createMock(TicketRepository::class);
        $statuses  = $this->createMock(StatusRepository::class);

        $apiClient->expects($this->once())->method('getStatuses')->willReturn([
            ['name' => 's1', 'title' => 'Open', 'description' => null],
        ]);
        $apiClient->expects($this->once())->method('getContacts')->willReturn([
            ['name' => 'c1', 'title' => 'Alice', 'description' => null, 'created' => '2024-01-01 00:00:00', 'edited' => '2024-01-01 00:00:00'],
        ]);
        $apiClient->expects($this->once())->method('getTickets')->willReturn([
            ['name' => 't1', 'title' => 'Issue A', 'description' => null, 'created' => '2024-01-01 00:00:00', 'edited' => '2024-01-01 00:00:00'],
        ]);

        $statuses->expects($this->once())->method('upsert');
        $contacts->expects($this->once())->method('upsert');
        $tickets->expects($this->once())->method('upsert');

        $service = new SyncService($apiClient, $contacts, $tickets, $statuses, new NullLogger());
        $service->run();
    }

    public function testRunContinuesWhenApiThrowsException(): void
    {
        $apiClient = $this->createMock(DaktelaApiClient::class);
        $contacts  = $this->createMock(ContactRepository::class);
        $tickets   = $this->createMock(TicketRepository::class);
        $statuses  = $this->createMock(StatusRepository::class);

        $apiClient->method('getStatuses')->willThrowException(new \RuntimeException('API down'));
        $apiClient->method('getContacts')->willThrowException(new \RuntimeException('API down'));
        $apiClient->method('getTickets')->willThrowException(new \RuntimeException('API down'));

        $statuses->expects($this->never())->method('upsert');
        $contacts->expects($this->never())->method('upsert');
        $tickets->expects($this->never())->method('upsert');

        $service = new SyncService($apiClient, $contacts, $tickets, $statuses, new NullLogger());

        // should not throw
        $service->run();
    }
}
