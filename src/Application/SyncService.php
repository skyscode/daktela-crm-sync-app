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
 * Daktela data model mapping:
 *  - /statuses  → call-outcome statuses; all synced as type='contact'.
 *  - /contacts  → address-book persons, mapped to the assessment's "CRM Contact" entity.
 *                 contacts.json has no workflow status field, so each contact is assigned
 *                 a status via crc32(external_id) % count(statuses). Deterministic per
 *                 contact (idempotent across sync runs), evenly distributed across the
 *                 18 statuses. Documented in README as a known data-model trade-off.
 *  - /tickets   → support tickets. Status resolution: prefer ticket.statuses[0].name;
 *                 fall back to synthetic 'ticket_stage_<stage>' (type='ticket').
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

    public function run(): array
    {
        set_time_limit(0);

        $this->logger->info('Sync cycle started');
        $syncedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $results = [];
        $results['statuses']         = $this->syncContactStatuses($syncedAt);
        $results['ticket_statuses']  = $this->syncTicketStatuses($syncedAt);
        $results['contacts']         = $this->syncContacts($syncedAt);
        $results['tickets']          = $this->syncTickets($syncedAt);

        $this->logger->info('Sync cycle completed');
        return $results;
    }

    private function syncContactStatuses(string $syncedAt): array
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
            return ['count' => $count, 'error' => null];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync statuses: ' . $e->getMessage());
            return ['count' => $count, 'error' => $e->getMessage()];
        }
    }

    private function syncContacts(string $syncedAt): array
    {
        $this->logger->info('Syncing contacts from contacts.json');
        $count     = 0;
        $statusIds = array_values($this->statuses->mapByType('contact'));

        if (empty($statusIds)) {
            $msg = 'Cannot sync contacts: no contact-type statuses available';
            $this->logger->error($msg);
            return ['count' => 0, 'error' => $msg];
        }

        try {
            $items = $this->apiClient->getContacts();

            $this->contacts->beginTransaction();
            foreach ($items as $item) {
                $statusId = $statusIds[abs(crc32((string) $item['name'])) % count($statusIds)];
                $this->contacts->upsert(Contact::fromApiResponse($item, $syncedAt, (int) $statusId));
                $count++;
            }
            $this->contacts->commit();

            $this->logger->info("Contacts synced: {$count}");
            return ['count' => $count, 'error' => null];
        } catch (\Throwable $e) {
            if ($this->contacts->inTransaction()) {
                $this->contacts->rollBack();
            }
            $this->logger->error('Failed to sync contacts: ' . $e->getMessage());
            return ['count' => $count, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
        }
    }

    /**
     * Pre-seed the 4 ticket-type statuses from Daktela's documented stage enum.
     * Doing this before syncTickets() ensures every ticket has a real FK target
     * and avoids touching statuses.json rows (which stay type='contact').
     */
    private function syncTicketStatuses(string $syncedAt): array
    {
        $this->logger->info('Seeding ticket statuses from stage enum');
        $stages = [
            'OPEN'    => 'Open',
            'WAIT'    => 'Waiting',
            'CLOSE'   => 'Closed',
            'ARCHIVE' => 'Archived',
        ];

        try {
            foreach ($stages as $name => $title) {
                $this->statuses->upsert(Status::fromApiResponse(
                    ['name' => $name, 'title' => $title, 'description' => null],
                    $syncedAt,
                    'ticket',
                ));
            }
            $this->logger->info('Ticket statuses seeded: ' . count($stages));
            return ['count' => count($stages), 'error' => null];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to seed ticket statuses: ' . $e->getMessage());
            return ['count' => 0, 'error' => $e->getMessage()];
        }
    }

    private function syncTickets(string $syncedAt): array
    {
        $this->logger->info('Syncing tickets');
        $count     = 0;
        $statusMap = $this->statuses->mapByType('ticket');

        try {
            $items = $this->apiClient->getTickets();

            foreach ($items as $item) {
                $stage = strtoupper((string) ($item['stage'] ?? ''));
                if (!isset($statusMap[$stage])) {
                    throw new \RuntimeException("Ticket {$item['name']} has unknown stage '{$stage}'");
                }

                $this->tickets->upsert(Ticket::fromApiResponse($item, $syncedAt, (int) $statusMap[$stage]));
                $count++;
            }

            $this->logger->info("Tickets synced: {$count}");
            return ['count' => $count, 'error' => null];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync tickets: ' . $e->getMessage());
            return ['count' => $count, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
        }
    }
}
