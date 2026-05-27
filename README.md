# CRM & Ticketing Integration Service

A PHP 8.3 application that syncs contacts, tickets, and statuses from the Daktela CRM/Helpdesk API into a local MySQL database, exposes a read-only REST API over that data, and runs a background daemon that keeps the mirror up to date every hour.

- **Git repository:** _TODO: add URL_
- **Live API:** _TODO: add URL_

---

## Stack

| Concern | Choice |
|---|---|
| Language | PHP 8.3 (strict types throughout) |
| Framework | None — clean OOP PHP |
| HTTP client | Guzzle 7 |
| Database | MySQL 8 / InnoDB |
| Logging | Monolog 3 (PSR-3) |
| Tests | PHPUnit 11 |
| Deployment | Railway (Docker + Procfile) |

---

## Running locally

### Prerequisites

- PHP 8.2+ with extensions: `pdo_mysql`, `pcntl`, `mbstring`
- Composer
- MySQL database

### Setup

```bash
git clone <repo-url>
cd crm-task-daktela
composer install
cp .env.example .env
# fill in .env with your DB credentials and Daktela API token
```

### Run the web server

```bash
php -S 0.0.0.0:8080 index.php
```

The API is now available at `http://localhost:8080/api/contacts`, etc.

The schema and triggers are created automatically on the first HTTP request (handled by `Migrator::run()` in the front controller).

### Run the daemon

In a second terminal:

```bash
php daemon.php
```

The daemon starts immediately, runs a full sync, then sleeps for `SYNC_INTERVAL` seconds (default: 3600) before repeating. Send `SIGTERM` or press `Ctrl+C` to shut it down cleanly.

### Run the tests

```bash
vendor/bin/phpunit
```

---

## API reference

All responses are JSON. Error responses always include `{"error": "<message>"}`.

### Contacts

| Method | Path | Query params |
|---|---|---|
| GET | `/api/contacts` | `page`, `limit`, `status_id` |
| GET | `/api/contacts/{external_id}` | — |

### Tickets

| Method | Path | Query params |
|---|---|---|
| GET | `/api/tickets` | `page`, `limit`, `status_id` |
| GET | `/api/tickets/{external_id}` | — |

### Statuses

| Method | Path | Query params |
|---|---|---|
| GET | `/api/statuses` | `page`, `limit`, `type` (`contact`\|`ticket`) |

### Utility

| Method | Path | Description |
|---|---|---|
| POST | `/api/sync` | Trigger an immediate sync; returns counts and any errors |
| GET | `/api/debug` | Proxy raw Daktela API response for a given `endpoint` param |

**Pagination** — all list endpoints accept `page` (default 1) and `limit` (default 20, max 100). Responses include a `meta` object:

```json
{
  "data": [...],
  "meta": { "page": 1, "limit": 20, "total": 1027, "pages": 52 }
}
```

**Status reference shape** — `status_id` is the integer FK to `statuses.id`, matching standard REST convention. Clients can fetch the full status row by ID via `GET /api/statuses` if they need the title/type.

Example contact response:
```json
{
  "external_id": "contact_69f8b01583e76406993169",
  "title": "PL number test",
  "description": "",
  "status_id": 5,
  "created_at": "2026-05-04 16:41:25",
  "updated_at": "2026-05-04 16:41:25",
  "synced_at": "2026-05-27 20:39:05"
}
```

Example ticket response:
```json
{
  "external_id": "2598",
  "title": "sada",
  "description": "kutis@kvhaustechnik.de",
  "status_id": 19,
  "created_at": "2025-03-30 22:46:43",
  "updated_at": "2025-06-03 10:37:48",
  "synced_at": "2026-05-27 20:39:05"
}
```

---

## Dependencies

No application framework was used. Laravel or Symfony would solve problems this project doesn't have and would obscure the design decisions the assessment is meant to evaluate. Each dependency solves exactly one problem and is the community standard for that problem.

| Package | Why |
|---|---|
| `vlucas/phpdotenv` | De-facto standard for `.env` loading in non-framework PHP. Lightweight, widely maintained, keeps credentials out of code. |
| `guzzlehttp/guzzle` | The most widely used HTTP client in the PHP ecosystem. Handles connection timeouts and SSL verification out of the box — both needed here. The alternative (native `curl`) requires significant boilerplate to handle errors and timeouts properly. |
| `monolog/monolog` | The task explicitly requires PSR-3. Monolog is the reference implementation of that standard, used by Laravel, Symfony, and virtually every major PHP project. |
| `phpunit/phpunit` | The task mentions PHPUnit by name as the expected testing tool. |

---

## Architectural decisions

### No framework

The task is primarily about sync correctness and API design, not about routing middleware chains. A custom router, PDO wrapper, and four dependency-injected classes is enough surface area to demonstrate layered design without framework boilerplate obscuring it. The layers are:

- **Domain** — plain PHP value objects (`Contact`, `Ticket`, `Status`) with `fromApiResponse()` factory methods. Domain objects implement `JsonSerializable` — a PHP built-in interface that `json_encode()` is aware of, so serialization is automatic without manual `toArray()` calls everywhere.
- **Application** — `SyncService` orchestrates one sync cycle; knows nothing about HTTP
- **Infrastructure** — `DaktelaApiClient` (Guzzle), repositories (PDO), `Migrator`, `Router`
- **Presentation** — three controllers, `api.php` front controller

### Sync order

Statuses are always synced before contacts and tickets. The `contacts` and `tickets` tables have a `status_id` foreign key referencing `statuses.id` — inserting a row with a `status_id` that doesn't exist yet would violate the FK constraint. The order is:

1. `syncContactStatuses()` — fetch `statuses.json`, upsert all 18 as `type='contact'`
2. `syncTicketStatuses()` — pre-seed the 4 ticket-type statuses from the documented `stage` enum
3. `syncContacts()` — fetch `contacts.json`, upsert with hash-based status assignment
4. `syncTickets()` — fetch `tickets.json`, upsert with status resolved from each ticket's `stage` field

### Data source mapping

Daktela's API doesn't perfectly fit the assessment's data model, so the mapping is documented explicitly:

| Local table | Source endpoint / data | Notes |
|---|---|---|
| `contacts` | `GET /api/v6/contacts.json` (1027 rows) | Address-book persons — matches the task's "CRM Contact" entity by name and Daktela's own documentation ("Contacts represents the person from your address book or CRM"). |
| `statuses` (type=contact) | `GET /api/v6/statuses.json` (18 rows) | Daktela's call-outcome / CRM-workflow statuses (Lead, Not available, Call later, etc.). |
| `statuses` (type=ticket) | Pre-seeded from Daktela's documented `stage` enum (4 rows: OPEN/WAIT/CLOSE/ARCHIVE with titles Open/Waiting/Closed/Archived) | Daktela has no separate ticket-statuses endpoint; the documented enum is the authoritative source. |
| `tickets` | `GET /api/v6/tickets.json` (4 rows) | Each ticket's `status_id` is resolved from its `stage` field, mapped to the pre-seeded ticket-type status. |

### Contact → status assignment

`contacts.json` does not carry a workflow-status field on the contact itself — Daktela models status through `crmRecords` and `campaignsRecords`, both of which are too sparse on this instance (14 crmRecords, 0 campaignsRecords, only 1 with a non-null status) to be a meaningful source. Rather than leave 99% of contacts statusless, each contact is assigned a status deterministically via `crc32(contact.name) % 18`:

- **Idempotent** — same contact always lands on the same status across sync runs (essential for the task's no-duplicate guarantee).
- **Evenly distributed** — ~57 contacts per status across the 18 available.
- **Order-independent** — re-ordering of the API response doesn't shift anyone's assignment.

This is a documented trade-off: the contact-side mapping is artificial, but the schema constraint (required `status_id`) is satisfied and the assignment is stable.

### Status type enforcement

The task requires that a ticket cannot have a contact-type status and vice versa. This is enforced at two layers:

1. **Application layer** — `SyncService` explicitly sets the `type` field when upserting statuses, and resolves each entity's status from the matching type.
2. **Database layer** — four `BEFORE INSERT / BEFORE UPDATE` triggers on `contacts` and `tickets` raise `SQLSTATE '45000'` if the referenced status's `type` does not match the entity type. MySQL does not support column-level check constraints across tables, so triggers are the correct mechanism here.

### Write endpoints

No `POST / PUT / DELETE` endpoints are exposed. The Daktela instance is the system of record. Writing to the local mirror without propagating back would silently diverge from the source and be overwritten on the next sync cycle. Writing back to Daktela would require scoping, auth, and validation work that goes beyond the assessment scope. Read-only keeps the contract clean and honest.

### Resilience strategy

Each of the three sync phases (statuses, contacts, tickets) is wrapped in an independent try/catch. If the tickets phase fails after 200 rows are already upserted, those 200 rows are persisted and the error is logged with full context. The next daemon cycle retries from scratch. This is partial-failure handling: a single broken record or a mid-cycle API timeout does not roll back the work already done.

The daemon itself wraps the entire `SyncService::run()` call in a separate try/catch, so an unhandled exception in one cycle does not kill the process — it logs the error and sleeps until the next interval.

### Auto-migration

`Migrator::run()` is called on every HTTP request and at daemon startup. It creates tables and triggers if they do not exist (`CREATE TABLE IF NOT EXISTS`, `DROP TRIGGER IF EXISTS` + recreate). This makes the app self-provisioning on a fresh database and removes the need for a separate migration command during deployment.

---

## Deployment

The application is deployed on [Railway](https://railway.app).

### How it is wired

Railway uses the `Procfile` to define the process:

```
web: php -S 0.0.0.0:$PORT index.php
```

The web process handles all HTTP traffic. The sync daemon is **not** run as a separate Railway service in this deployment — instead the `POST /api/sync` endpoint is available for manual triggering, and the daemon can be started separately if a worker dyno is provisioned. The `Dockerfile` is provided for containerised environments that support multi-process deployments.

For the daemon to run continuously alongside the web server, start it as a second process:

```bash
php daemon.php &
```

Or deploy it as a separate Railway service pointing to the same database and environment variables.

### Accessing logs

Logs are written to two destinations simultaneously (configured in both `daemon.php` and `api.php`):

1. **`logs/app.log`** — persistent file on disk, one JSON-structured line per event
2. **stdout** — captured by Railway's log stream, visible in the Railway dashboard under the service's "Logs" tab

Each log line includes the timestamp, level, message, and a context array (e.g., `{"interval": 3600}` for daemon start, `{"error": "..."}` for failures).

---

## Git history

All commits go directly to `main`. The project was built by filling in stubs rather than developing isolated features, so feature branches would add overhead without meaningful isolation benefit for a solo project of this scope. Each commit maps to one logical build step (config, schema, PDO, domain models, repositories, etc.), keeping the history readable without branch noise.

---

## What I would do with more time

- **Retry with exponential backoff** — currently the daemon simply waits the full interval and retries. A per-request retry loop with jitter would recover faster from transient Daktela API errors (timeouts, 5xx) without hammering the API.
- **Delta sync** — the current implementation fetches all records every cycle. Daktela's API supports `updatedFrom` / `updatedTo` filters; using them would reduce data transfer and sync time significantly once the initial load is done.
- **Separate daemon worker on Railway** — add a second service in `railway.json` with `startCommand: php daemon.php` so the background process is managed and restarted by Railway independently from the web server.
- **Broader test coverage** — the current tests cover the API list/detail endpoints and core SyncService logic with mocked dependencies. Tests against the trigger constraints and the Migrator idempotency would increase confidence.
- **Rate limiting and queue** — large Daktela instances can return thousands of records. A bounded queue between the API client and the repository would cap memory usage during a single cycle.
