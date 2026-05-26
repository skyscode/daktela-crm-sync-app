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

/**
 * Orchestrates one full sync cycle: fetch from Daktela API → upsert into DB.
 *
 * Daktela data model notes (discovered from live API):
 *  - /statuses  → call-outcome statuses; synced as type='contact'
 *  - /contacts  → CRM contacts; Daktela has no direct status FK on contacts,
 *                 so we assign the first available contact status as a default.
 *  - /tickets   → support tickets; workflow status comes from the 'stage' field
 *                 (OPEN, CLOSED, etc.); we create type='ticket' statuses from
 *                 unique stage values on-the-fly during the ticket sync.
 */
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

        $this->syncContactStatuses($syncedAt);

        $contactStatusMap = $this->statuses->mapByType('contact');
        $this->syncContacts($syncedAt, $contactStatusMap);

        $this->syncTickets($syncedAt);

        $this->logger->info('Sync cycle completed');
    }

    private function syncContactStatuses(string $syncedAt): void
    {
        $this->logger->info('Syncing statuses');
        $count = 0;

        try {
            $items = $this->apiClient->getStatuses();

            foreach ($items as $item) {
                $this->statuses->upsert(Status::fromApiResponse($item, $syncedAt, 'contact'));
                $count++;
            }

            $this->logger->info("Statuses synced: {$count}");
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync statuses: ' . $e->getMessage());
        }
    }

    private function syncContacts(string $syncedAt, array $contactStatusMap): void
    {
        $this->logger->info('Syncing contacts');
        $count = 0;

        $defaultStatusId = !empty($contactStatusMap) ? (int) reset($contactStatusMap) : null;

        if ($defaultStatusId === null) {
            $this->logger->error('No contact statuses available — skipping contact sync');
            return;
        }

        try {
            $items = $this->apiClient->getContacts();

            foreach ($items as $item) {
                $this->contacts->upsert(Contact::fromApiResponse($item, $syncedAt, $defaultStatusId));
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
        $count    = 0;
        $stageMap = $this->statuses->mapByType('ticket');

        try {
            $items = $this->apiClient->getTickets();

            foreach ($items as $item) {
                $stage    = (string) ($item['stage'] ?? 'OPEN');
                $stageKey = 'stage_' . strtolower($stage);

                if (!isset($stageMap[$stageKey])) {
                    $this->statuses->upsert(Status::fromApiResponse([
                        'name'        => $stageKey,
                        'title'       => ucwords(strtolower(str_replace('_', ' ', $stage))),
                        'description' => null,
                    ], $syncedAt, 'ticket'));

                    $stageMap[$stageKey] = $this->statuses->findByExternalId($stageKey)->id;
                }

                $this->tickets->upsert(Ticket::fromApiResponse($item, $syncedAt, (int) $stageMap[$stageKey]));
                $count++;
            }

            $this->logger->info("Tickets synced: {$count}");
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync tickets: ' . $e->getMessage());
        }
    }
}
