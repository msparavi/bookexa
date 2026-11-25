-- ============================================================================
-- Migration 003: Media Storage & CDN Synchronization Table
-- Import via phpMyAdmin on existing installations before enabling CDN sync.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `media_sync` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rel_path` VARCHAR(255) NOT NULL,
    `local_exists` TINYINT(1) NOT NULL DEFAULT 1,
    `cdn_synced` TINYINT(1) NOT NULL DEFAULT 0,
    `cdn_synced_at` DATETIME DEFAULT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_error` VARCHAR(255) DEFAULT NULL,
    `size` INT UNSIGNED NOT NULL DEFAULT 0,
    `sha1` CHAR(40) DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_media_rel_path` (`rel_path`),
    KEY `idx_media_sync_status` (`cdn_synced`, `local_exists`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
