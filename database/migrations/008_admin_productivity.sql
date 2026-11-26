-- Bookexa Migration 008: Admin Productivity Features (RBAC, 2FA/TOTP, Sessions, CSV/Prep, Inbox Reply, Scheduled Publishing, Search Analytics)

ALTER TABLE `admins`
    MODIFY COLUMN `role` ENUM('superadmin', 'editor', 'support', 'analyst') NOT NULL DEFAULT 'superadmin',
    ADD COLUMN `totp_secret` VARCHAR(64) DEFAULT NULL AFTER `must_change_password`,
    ADD COLUMN `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `totp_secret`,
    ADD COLUMN `backup_codes_json` TEXT DEFAULT NULL AFTER `totp_enabled`;

CREATE TABLE IF NOT EXISTS `admin_sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id` INT UNSIGNED NOT NULL,
    `session_token` CHAR(64) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `last_active_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_as_token` (`session_token`),
    KEY `idx_as_admin` (`admin_id`, `last_active_at`),
    CONSTRAINT `fk_as_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `contact_messages`
    ADD COLUMN `reply_message` TEXT DEFAULT NULL AFTER `status`,
    ADD COLUMN `replied_by` INT UNSIGNED DEFAULT NULL AFTER `reply_message`,
    ADD COLUMN `replied_at` DATETIME DEFAULT NULL AFTER `replied_by`;

ALTER TABLE `banners`
    ADD COLUMN `publish_at` DATETIME DEFAULT NULL AFTER `ends_at`,
    ADD COLUMN `unpublish_at` DATETIME DEFAULT NULL AFTER `publish_at`;

ALTER TABLE `folders`
    ADD COLUMN `publish_at` DATETIME DEFAULT NULL AFTER `is_active`,
    ADD COLUMN `unpublish_at` DATETIME DEFAULT NULL AFTER `publish_at`;

ALTER TABLE `items`
    ADD COLUMN `publish_at` DATETIME DEFAULT NULL AFTER `is_active`,
    ADD COLUMN `unpublish_at` DATETIME DEFAULT NULL AFTER `publish_at`;

CREATE TABLE IF NOT EXISTS `search_queries` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `query_text` VARCHAR(190) NOT NULL,
    `lang` VARCHAR(5) NOT NULL DEFAULT 'bn',
    `hits` INT UNSIGNED NOT NULL DEFAULT 1,
    `last_searched_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_sq_query` (`query_text`),
    KEY `idx_sq_hits` (`hits`, `last_searched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('admin_ip_allowlist', ''),
('last_cron_at', ''),
('last_daily_backup_at', '')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;
