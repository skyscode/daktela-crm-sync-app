<?php

declare(strict_types=1);

namespace App\Infrastructure;

class Migrator
{
    public function __construct(private \PDO $pdo) {}

    public function run(): void
    {
        $this->runSchema();
        $this->addMissingColumns();
        $this->installTriggers();
    }

    private function runSchema(): void
    {
        $this->pdo->exec(file_get_contents(__DIR__ . '/../../database/scheme.sql'));
    }

    private function addMissingColumns(): void
    {
        try { $this->pdo->exec("ALTER TABLE statuses ADD COLUMN type ENUM('contact','ticket') NULL"); } catch (\PDOException $e) {}
        try { $this->pdo->exec("ALTER TABLE statuses ADD INDEX idx_statuses_type (type)"); } catch (\PDOException $e) {}

        $this->pdo->exec("UPDATE statuses SET type = 'contact' WHERE type IS NULL");
        try { $this->pdo->exec("ALTER TABLE statuses MODIFY COLUMN type ENUM('contact','ticket') NOT NULL"); } catch (\PDOException $e) {}

        $this->pdo->exec("UPDATE statuses SET external_id = 'ticket_stage_open'   WHERE external_id = 'stage_open'   AND type = 'ticket'");
        $this->pdo->exec("UPDATE statuses SET external_id = 'ticket_stage_close'  WHERE external_id = 'stage_close'  AND type = 'ticket'");
        $this->pdo->exec("UPDATE statuses SET external_id = 'ticket_stage_closed' WHERE external_id = 'stage_closed' AND type = 'ticket'");
    }

    private function installTriggers(): void
    {
        foreach (['trg_contacts_status_type_insert', 'trg_contacts_status_type_update', 'trg_tickets_status_type_insert', 'trg_tickets_status_type_update'] as $trigger) {
            $this->pdo->exec("DROP TRIGGER IF EXISTS {$trigger}");
        }

        $this->pdo->exec("
            CREATE TRIGGER trg_contacts_status_type_insert
            BEFORE INSERT ON contacts FOR EACH ROW
            BEGIN
                DECLARE v_type VARCHAR(20);
                IF NEW.status_id IS NOT NULL THEN
                    SELECT type INTO v_type FROM statuses WHERE id = NEW.status_id;
                    IF v_type IS NULL OR v_type != 'contact' THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Contact status_id must reference a status of type=contact';
                    END IF;
                END IF;
            END
        ");

        $this->pdo->exec("
            CREATE TRIGGER trg_contacts_status_type_update
            BEFORE UPDATE ON contacts FOR EACH ROW
            BEGIN
                DECLARE v_type VARCHAR(20);
                IF NEW.status_id IS NOT NULL THEN
                    SELECT type INTO v_type FROM statuses WHERE id = NEW.status_id;
                    IF v_type IS NULL OR v_type != 'contact' THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Contact status_id must reference a status of type=contact';
                    END IF;
                END IF;
            END
        ");

        $this->pdo->exec("
            CREATE TRIGGER trg_tickets_status_type_insert
            BEFORE INSERT ON tickets FOR EACH ROW
            BEGIN
                DECLARE v_type VARCHAR(20);
                IF NEW.status_id IS NOT NULL THEN
                    SELECT type INTO v_type FROM statuses WHERE id = NEW.status_id;
                    IF v_type IS NULL OR v_type != 'ticket' THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Ticket status_id must reference a status of type=ticket';
                    END IF;
                END IF;
            END
        ");

        $this->pdo->exec("
            CREATE TRIGGER trg_tickets_status_type_update
            BEFORE UPDATE ON tickets FOR EACH ROW
            BEGIN
                DECLARE v_type VARCHAR(20);
                IF NEW.status_id IS NOT NULL THEN
                    SELECT type INTO v_type FROM statuses WHERE id = NEW.status_id;
                    IF v_type IS NULL OR v_type != 'ticket' THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Ticket status_id must reference a status of type=ticket';
                    END IF;
                END IF;
            END
        ");
    }
}
