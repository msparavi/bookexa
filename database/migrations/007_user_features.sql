-- Bookexa Migration 007: Public browse FULLTEXT/stats, reader bookmarks/TOC/search index, devices & SMS OTP

ALTER TABLE `folders`
    ADD FULLTEXT KEY `ft_folders_search` (`name_bn`, `name_en`, `description_bn`, `description_en`);

ALTER TABLE `items`
    ADD COLUMN `view_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `total_pages`,
    ADD COLUMN `read_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `view_count`,
    ADD COLUMN `toc_json` MEDIUMTEXT DEFAULT NULL AFTER `read_count`,
    ADD FULLTEXT KEY `ft_items_search` (`title_bn`, `title_en`, `author_bn`, `author_en`, `description_bn`, `description_en`);

ALTER TABLE `reading_progress`
    ADD COLUMN `time_spent_sec` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `total_pages`,
    ADD COLUMN `is_finished` TINYINT(1) NOT NULL DEFAULT 0 AFTER `time_spent_sec`;

CREATE TABLE IF NOT EXISTS `search_misses` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `query_text` VARCHAR(190) NOT NULL,
    `lang` VARCHAR(5) NOT NULL DEFAULT 'bn',
    `ip_address` VARCHAR(45) NOT NULL,
    `hits` INT UNSIGNED NOT NULL DEFAULT 1,
    `last_searched_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_sm_query` (`query_text`),
    KEY `idx_sm_time` (`last_searched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recently_viewed` (
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `viewed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `item_id`),
    KEY `idx_rv_user_time` (`user_id`, `viewed_at`),
    CONSTRAINT `fk_rv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rv_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reader_bookmarks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `page_num` INT UNSIGNED NOT NULL,
    `label` VARCHAR(120) NOT NULL DEFAULT '',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_rb_user_item_page` (`user_id`, `item_id`, `page_num`),
    KEY `idx_rb_lookup` (`user_id`, `item_id`),
    CONSTRAINT `fk_rb_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rb_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `item_page_texts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_id` INT UNSIGNED NOT NULL,
    `page_num` INT UNSIGNED NOT NULL,
    `is_staging` TINYINT(1) NOT NULL DEFAULT 0,
    `norm_text` MEDIUMTEXT NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ipt_item_page_stage` (`item_id`, `page_num`, `is_staging`),
    KEY `idx_ipt_item_stage` (`item_id`, `is_staging`),
    FULLTEXT KEY `ft_ipt_text` (`norm_text`),
    CONSTRAINT `fk_ipt_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_devices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `device_token` VARCHAR(128) NOT NULL,
    `device_fingerprint` VARCHAR(64) DEFAULT NULL,
    `device_label` VARCHAR(120) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `last_active_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ud_token` (`device_token`),
    KEY `idx_ud_user` (`user_id`, `last_active_at`),
    CONSTRAINT `fk_ud_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `otp_verifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `purpose` ENUM('phone_login', 'change_email', 'change_phone') NOT NULL,
    `target` VARCHAR(190) NOT NULL,
    `code_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `verified_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_otp_lookup` (`purpose`, `target`, `expires_at`),
    CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('max_devices_per_account', '2'),
('sms_gateway_enabled', '0'),
('sms_gateway_driver', 'none'),
('sms_gateway_sandbox', '1'),
('sms_gateway_api_url', ''),
('sms_gateway_api_key', ''),
('sms_gateway_sender_id', 'Bookexa')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;
