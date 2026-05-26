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
 * Daktela data model mapping (discovered from live API):
 *  - /statuses    → master list of call-outcome statuses; all synced as type='contact'.
 *  - /crmRecords  → CRM workflow records, mapped to the assessment's "Contact" entity.
 *                   Status resolution: prefer crmRecord.status.name; fall back to
 *                   synthetic 'crm_record_stage_<stage>' (type='contact').
 *  - /tickets     → support tickets. Status resolution: prefer ticket.statuses[0].name;
 *                   fall back to synthetic 'ticket_stage_<stage>' (type='ticket').
 *
 * The /contacts endpoint is intentionally NOT used: it carries address-book identity
 * records without a workflow status field. /crmRecords is the status-bearing CRM object.
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
        $this->syncContacts($syncedAt);
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

    private function syncContacts(string $syncedAt): void
    {
        $this->logger->info('Syncing contacts from crmRecords');
        $count     = 0;
        $statusMap = $this->statuses->mapByType('contact');

        try {
            $items = $this->apiClient->getCrmRecords();

            foreach ($items as $item) {
                $statusExternalId = $this->resolveCrmRecordStatusExternalId($item);

                if (!isset($statusMap[$statusExternalId])) {
                    $this->statuses->upsert(Status::fromApiResponse([
                        'name'        => $statusExternalId,
                        'title'       => $item['status']['title'] ?? ucwords(str_replace('_', ' ', $statusExternalId)),
                        'description' => $item['status']['description'] ?? null,
                    ], $syncedAt, 'contact'));
                    $statusMap[$statusExternalId] = $this->statuses->findByExternalId($statusExternalId)->id;
                }

                $this->contacts->upsert(Contact::fromApiResponse($item, $syncedAt, (int) $statusMap[$statusExternalId]));
                $count++;
            }

            $this->logger->info("Contacts synced: {$count}");
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync contacts: ' . $e->getMessage());
        }
    }

    private function resolveCrmRecordStatusExternalId(array $record): string
    {
        if (!empty($record['status']['name'])) {
            return (string) $record['status']['name'];
        }

        if (!empty($record['stage'])) {
            return 'crm_record_stage_' . strtolower($record['stage']);
        }

        throw new \RuntimeException("CRM record {$record['name']} has no status or stage");
    }

    private function syncTickets(string $syncedAt): void
    {
        $this->logger->info('Syncing tickets');
        $count     = 0;
        $statusMap = $this->statuses->mapByType('ticket');

        try {
            $items = $this->apiClient->getTickets();

            foreach ($items as $item) {
                $statusExternalId = $this->resolveTicketStatusExternalId($item);

                if (!isset($statusMap[$statusExternalId])) {
                    $embedded = $item['statuses'][0] ?? null;
                    $this->statuses->upsert(Status::fromApiResponse([
                        'name'        => $statusExternalId,
                        'title'       => $embedded['title'] ?? ucwords(str_replace('_', ' ', $statusExternalId)),
                        'description' => $embedded['description'] ?? null,
                    ], $syncedAt, 'ticket'));
                    $statusMap[$statusExternalId] = $this->statuses->findByExternalId($statusExternalId)->id;
                }

                $this->tickets->upsert(Ticket::fromApiResponse($item, $syncedAt, (int) $statusMap[$statusExternalId]));
                $count++;
            }

            $this->logger->info("Tickets synced: {$count}");
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync tickets: ' . $e->getMessage());
        }
    }

    private function resolveTicketStatusExternalId(array $ticket): string
    {
        if (!empty($ticket['statuses'][0]['name'])) {
            return (string) $ticket['statuses'][0]['name'];
        }

        if (!empty($ticket['stage'])) {
            return 'ticket_stage_' . strtolower($ticket['stage']);
        }

        throw new \RuntimeException("Ticket {$ticket['name']} has no status or stage");
    }
}
