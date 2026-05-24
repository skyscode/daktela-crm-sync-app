<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Contact;
use App\Domain\Ticket;
use App\Domain\Status;
use App\Infrastructure\External\DaktelaApiClient;
use App\Infrastructure\Persistence\ContactRepository;
use App\Infrastructure\Persistence\TicketRepository;
use App\Infrastructure\Persistence\StatusRepository;
use Psr\Log\LoggerInterface;

// Doing one full sync cycle: fetch from Daktela API → upsert into DB.
// Called by daemon.php on every interval tick.
// Depends on DaktelaApiClient and the three repositories (injected via constructor).
// Logs cycle start/end, counts inserted/updated/skipped per entity, and any errors.
class SyncService
{
    public function __construct(
        private readonly DaktelaApiClient  $apiClient,
        private readonly ContactRepository $contacts,
        private readonly TicketRepository  $tickets,
        private readonly StatusRepository  $statuses,
        private readonly LoggerInterface   $logger,
    ) {}

    public function run(): void
    {
        $this->logger->info('Sync cycle started');
        $syncedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->syncStatuses($syncedAt);
        $this->syncContacts($syncedAt);
        $this->syncTickets($syncedAt);

        $this->logger->info('Sync cycle completed');
    }

    private function syncStatuses(string $syncedAt): void
    {
        $this->logger->info('Syncing statuses');
        $count = 0;

        try {
            $items = $this->apiClient->getStatuses();

            foreach ($items as $item) {
                $this->statuses->upsert(Status::fromApiResponse($item, $syncedAt));
                $count++;
            }

            $this->logger->info("Statuses synced: {$count}");
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync statuses: ' . $e->getMessage());
        }
    }

    private function syncContacts(string $syncedAt): void
    {
        $this->logger->info('Syncing contacts');
        $count = 0;

        try {
            $items = $this->apiClient->getContacts();

            foreach ($items as $item) {
                $this->contacts->upsert(Contact::fromApiResponse($item, $syncedAt));
                $count++;
            }

            $this->logger->info("Contacts synced: {$count}");
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync contacts: ' . $e->getMessage());
        }
    }

    private function syncTickets(string $syncedAt): void
    {
        $this->logger->info('Syncing tickets');
        $count = 0;

        try {
            $items = $this->apiClient->getTickets();

            foreach ($items as $item) {
                $this->tickets->upsert(Ticket::fromApiResponse($item, $syncedAt));
                $count++;
            }

            $this->logger->info("Tickets synced: {$count}");
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync tickets: ' . $e->getMessage());
        }
    }
}
