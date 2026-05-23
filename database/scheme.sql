-- =============================================================
-- statuses, contacts and tickets mirrored from Daktela with structure requested by the task

-- statuses
-- Lookup table for both contact and ticket statuses
-- Must be created before contacts and tickets due to FK references
-- =============================================================
CREATE TABLE IF NOT EXISTS `statuses` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id` VARCHAR(255) NOT NULL,  
  `title`       VARCHAR(500) NOT NULL,
  `description` TEXT NULL,
  `type`        ENUM('contact','ticket') NOT NULL,  
  `created_at`  DATETIME NOT NULL,
  `updated_at`  DATETIME NOT NULL,
  `synced_at`   DATETIME NOT NULL,     
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_statuses_external_id` (`external_id`),  
  KEY `idx_statuses_type` (`type`)     
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- contacts
-- status_id is nullable — if a status is deleted, the contact is
-- preserved with status_id set to NULL rather than being deleted.
-- =============================================================
CREATE TABLE IF NOT EXISTS `contacts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id` VARCHAR(255) NOT NULL,  
  `title`       VARCHAR(500) NOT NULL,
  `description` TEXT NULL,
  `status_id`   INT UNSIGNED NULL,      
  `created_at`  DATETIME NOT NULL,
  `updated_at`  DATETIME NOT NULL,
  `synced_at`   DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contacts_external_id` (`external_id`),
  KEY `idx_contacts_status_id` (`status_id`),  -- speeds up JOIN and status filter
  CONSTRAINT `fk_contacts_status` FOREIGN KEY (`status_id`)
    REFERENCES `statuses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- tickets
-- status_id is NOT NULL — a ticket must always have a status.
-- ON DELETE RESTRICT prevents removing a status still in use.
-- =============================================================
CREATE TABLE IF NOT EXISTS `tickets` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id` VARCHAR(255) NOT NULL,  
  `title`       VARCHAR(500) NOT NULL,
  `description` TEXT NULL,
  `status_id`   INT UNSIGNED NOT NULL,  
  `created_at`  DATETIME NOT NULL,
  `updated_at`  DATETIME NOT NULL,
  `synced_at`   DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tickets_external` (`external_id`),
  KEY `idx_tickets_status_id` (`status_id`),
  CONSTRAINT `fk_tickets_status` FOREIGN KEY (`status_id`)
    REFERENCES `statuses` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;