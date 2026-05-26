-- =============================================================
-- statuses, contacts and tickets mirrored from Daktela
-- statuses must be created first due to FK references
-- =============================================================

CREATE TABLE IF NOT EXISTS `statuses` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id` VARCHAR(255) NOT NULL,
  `title`       VARCHAR(500) NOT NULL,
  `description` TEXT NULL,
  `type`        ENUM('contact','ticket') NULL,
  `created_at`  DATETIME NOT NULL,
  `updated_at`  DATETIME NOT NULL,
  `synced_at`   DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_statuses_external_id` (`external_id`),
  KEY `idx_statuses_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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
  KEY `idx_contacts_status_id` (`status_id`),
  CONSTRAINT `fk_contacts_status` FOREIGN KEY (`status_id`)
    REFERENCES `statuses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `tickets` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id` VARCHAR(255) NOT NULL,
  `title`       VARCHAR(500) NOT NULL,
  `description` TEXT NULL,
  `status_id`   INT UNSIGNED NULL,
  `created_at`  DATETIME NOT NULL,
  `updated_at`  DATETIME NOT NULL,
  `synced_at`   DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tickets_external` (`external_id`),
  KEY `idx_tickets_status_id` (`status_id`),
  CONSTRAINT `fk_tickets_status` FOREIGN KEY (`status_id`)
    REFERENCES `statuses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
