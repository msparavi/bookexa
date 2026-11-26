-- ============================================================================
-- Bookexa — Complete Database Schema & Seed Data
-- Charset: utf8mb4 | Collation: utf8mb4_unicode_ci (Full Bangla + English)
-- Import via phpMyAdmin into the `bookexa` database.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `active_session_token` VARCHAR(128) DEFAULT NULL,
    `active_session_updated_at` DATETIME DEFAULT NULL,
    `reader_theme` ENUM('dark', 'sepia', 'light') DEFAULT NULL,
    `reader_zoom` DECIMAL(4,2) DEFAULT NULL,
    `reading_suspended_until` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_users_email` (`email`),
    KEY `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('superadmin', 'editor', 'support', 'analyst') NOT NULL DEFAULT 'superadmin',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at` DATETIME DEFAULT NULL,
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
    `totp_secret` VARCHAR(255) DEFAULT NULL,
    `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `backup_codes_json` TEXT DEFAULT NULL,
    `totp_last_step` BIGINT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS `banners` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title_bn` VARCHAR(200) NOT NULL,
    `title_en` VARCHAR(200) NOT NULL,
    `subtitle_bn` VARCHAR(300) DEFAULT NULL,
    `subtitle_en` VARCHAR(300) DEFAULT NULL,
    `cta_bn` VARCHAR(80) DEFAULT 'এখনই দেখুন',
    `cta_en` VARCHAR(80) DEFAULT 'Explore Now',
    `link_url` VARCHAR(255) NOT NULL DEFAULT '/pdf',
    `image` VARCHAR(255) NOT NULL,
    `mobile_image` VARCHAR(255) DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `starts_at` DATETIME DEFAULT NULL,
    `ends_at` DATETIME DEFAULT NULL,
    `publish_at` DATETIME DEFAULT NULL,
    `unpublish_at` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_banners_active_sort` (`is_active`, `sort_order`),
    KEY `idx_banners_pub` (`is_active`, `publish_at`, `unpublish_at`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `folders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `slug` VARCHAR(140) NOT NULL,
    `name_bn` VARCHAR(180) NOT NULL,
    `name_en` VARCHAR(180) NOT NULL,
    `description_bn` TEXT DEFAULT NULL,
    `description_en` TEXT DEFAULT NULL,
    `short_answer_bn` TEXT DEFAULT NULL,
    `short_answer_en` TEXT DEFAULT NULL,
    `intro_bn` MEDIUMTEXT DEFAULT NULL,
    `intro_en` MEDIUMTEXT DEFAULT NULL,
    `who_for_bn` TEXT DEFAULT NULL,
    `who_for_en` TEXT DEFAULT NULL,
    `faqs_json` MEDIUMTEXT DEFAULT NULL,
    `seo_title_bn` VARCHAR(220) DEFAULT NULL,
    `seo_title_en` VARCHAR(220) DEFAULT NULL,
    `seo_description_bn` VARCHAR(320) DEFAULT NULL,
    `seo_description_en` VARCHAR(320) DEFAULT NULL,
    `thumbnail` VARCHAR(255) DEFAULT NULL,
    `accent_color` VARCHAR(20) DEFAULT '#0F3D3E',
    `sort_order` INT NOT NULL DEFAULT 0,
    `subfolder_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `item_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `publish_at` DATETIME DEFAULT NULL,
    `unpublish_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_folders_parent_id` (`parent_id`),
    KEY `idx_folders_parent_active` (`parent_id`, `is_active`, `sort_order`),
    KEY `idx_folders_slug` (`slug`),
    KEY `idx_folders_active_sort` (`is_active`, `sort_order`),
    FULLTEXT KEY `ft_folders_search` (`name_bn`, `name_en`, `description_bn`, `description_en`),
    CONSTRAINT `fk_folders_parent` FOREIGN KEY (`parent_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `folder_id` INT UNSIGNED DEFAULT NULL,
    `slug` VARCHAR(160) NOT NULL,
    `title_bn` VARCHAR(220) NOT NULL,
    `title_en` VARCHAR(220) NOT NULL,
    `author_bn` VARCHAR(150) DEFAULT NULL,
    `author_en` VARCHAR(150) DEFAULT NULL,
    `isbn` VARCHAR(40) DEFAULT NULL,
    `publisher` VARCHAR(160) DEFAULT NULL,
    `edition` VARCHAR(80) DEFAULT NULL,
    `book_language` VARCHAR(60) DEFAULT NULL,
    `tags` VARCHAR(255) DEFAULT NULL,
    `faqs_json` MEDIUMTEXT DEFAULT NULL,
    `description_bn` TEXT DEFAULT NULL,
    `description_en` TEXT DEFAULT NULL,
    `short_answer_bn` TEXT DEFAULT NULL,
    `short_answer_en` TEXT DEFAULT NULL,
    `what_you_learn_bn` TEXT DEFAULT NULL,
    `what_you_learn_en` TEXT DEFAULT NULL,
    `who_for_bn` TEXT DEFAULT NULL,
    `who_for_en` TEXT DEFAULT NULL,
    `intro_bn` MEDIUMTEXT DEFAULT NULL,
    `intro_en` MEDIUMTEXT DEFAULT NULL,
    `who_for_bn` TEXT DEFAULT NULL,
    `who_for_en` TEXT DEFAULT NULL,
    `faqs_json` MEDIUMTEXT DEFAULT NULL,
    `seo_title_bn` VARCHAR(220) DEFAULT NULL,
    `seo_title_en` VARCHAR(220) DEFAULT NULL,
    `seo_description_bn` VARCHAR(320) DEFAULT NULL,
    `seo_description_en` VARCHAR(320) DEFAULT NULL,
    `cover` VARCHAR(255) DEFAULT NULL,
    `drive_link` TEXT DEFAULT NULL,
    `sample_drive_link` TEXT DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `original_price` DECIMAL(10,2) DEFAULT NULL,
    `is_free` TINYINT(1) NOT NULL DEFAULT 0,
    `sample_pages` INT UNSIGNED NOT NULL DEFAULT 4,
    `total_pages` INT UNSIGNED DEFAULT NULL,
    `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `read_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `toc_json` MEDIUMTEXT DEFAULT NULL,
    `pages_status` ENUM('unprocessed', 'processing', 'ready') NOT NULL DEFAULT 'unprocessed',
    `processing_status` ENUM('idle', 'processing', 'failed') NOT NULL DEFAULT 'idle',
    `processed_at` DATETIME DEFAULT NULL,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `publish_at` DATETIME DEFAULT NULL,
    `unpublish_at` DATETIME DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_items_slug` (`slug`),
    KEY `idx_items_slug` (`slug`),
    KEY `idx_items_folder_id` (`folder_id`),
    KEY `idx_items_folder_active` (`folder_id`, `is_active`),
    KEY `idx_items_active` (`is_active`, `is_featured`),
    KEY `idx_items_active_sort` (`is_active`, `sort_order`, `id`),
    KEY `idx_items_active_free` (`is_active`, `is_free`, `price`),
    KEY `idx_items_active_pop` (`is_active`, `read_count`, `view_count`),
    KEY `idx_items_active_pub` (`is_active`, `publish_at`, `unpublish_at`),
    KEY `idx_items_author_en` (`is_active`, `author_en`),
    KEY `idx_items_author_bn` (`is_active`, `author_bn`),
    FULLTEXT KEY `ft_items_search` (`title_bn`, `title_en`, `author_bn`, `author_en`, `description_bn`, `description_en`),
    CONSTRAINT `fk_items_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_code` VARCHAR(40) NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `original_amount` DECIMAL(10,2) DEFAULT NULL,
    `coupon_id` INT UNSIGNED DEFAULT NULL,
    `coupon_code` VARCHAR(40) DEFAULT NULL,
    `coupon_decremented` TINYINT(1) NOT NULL DEFAULT 0,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_method` VARCHAR(40) NOT NULL DEFAULT 'bkash',
    `gateway_driver` VARCHAR(40) DEFAULT NULL,
    `gateway_ref` VARCHAR(120) DEFAULT NULL,
    `sender_number` VARCHAR(40) DEFAULT NULL,
    `transaction_id` VARCHAR(80) DEFAULT NULL,
    `payment_screenshot` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'cancelled', 'expired') NOT NULL DEFAULT 'pending',
    `admin_note` VARCHAR(255) DEFAULT NULL,
    `internal_notes` TEXT DEFAULT NULL,
    `reviewed_by` INT UNSIGNED DEFAULT NULL,
    `reviewed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_orders_code` (`order_code`),
    UNIQUE KEY `uniq_orders_method_trx` (`payment_method`, `transaction_id`),
    KEY `idx_orders_user` (`user_id`),
    KEY `idx_orders_user_status` (`user_id`, `status`),
    KEY `idx_orders_status` (`status`),
    KEY `idx_orders_coupon` (`coupon_id`),
    KEY `idx_orders_sender` (`sender_number`),
    KEY `idx_orders_created` (`created_at`),
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_orders_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `library` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `order_id` INT UNSIGNED DEFAULT NULL,
    `granted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_library_user_item` (`user_id`, `item_id`),
    CONSTRAINT `fk_lib_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lib_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reader_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `token_hash` CHAR(64) NOT NULL,
    `jti` CHAR(32) NOT NULL DEFAULT '',
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `is_sample` TINYINT(1) NOT NULL DEFAULT 0,
    `session_id` VARCHAR(128) NOT NULL DEFAULT '',
    `session_token` VARCHAR(128) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_reader_token_hash` (`token_hash`),
    KEY `idx_rt_user_expires` (`user_id`, `expires_at`),
    CONSTRAINT `fk_rt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rt_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reading_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED DEFAULT NULL,
    `is_sample` TINYINT(1) NOT NULL DEFAULT 0,
    `action` VARCHAR(50) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `details` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rlogs_user_time` (`user_id`, `created_at`),
    KEY `idx_rlogs_item_time` (`item_id`, `created_at`),
    CONSTRAINT `fk_rlogs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rlogs_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(80) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `language_strings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lang_key` VARCHAR(100) NOT NULL,
    `text_bn` TEXT NOT NULL,
    `text_en` TEXT NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_lang_key` (`lang_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `rate_key` VARCHAR(120) NOT NULL,
    `hits` INT UNSIGNED NOT NULL DEFAULT 1,
    `window_start` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`rate_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_pr_token_hash` (`token_hash`),
    KEY `idx_pr_user` (`user_id`),
    CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reading_progress` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `last_page` INT UNSIGNED NOT NULL DEFAULT 1,
    `total_pages` INT UNSIGNED NOT NULL DEFAULT 1,
    `time_spent_sec` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_finished` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_rp_user_item` (`user_id`, `item_id`),
    CONSTRAINT `fk_rp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id` INT UNSIGNED DEFAULT NULL,
    `admin_name` VARCHAR(120) NOT NULL,
    `action` VARCHAR(80) NOT NULL,
    `target_type` VARCHAR(50) DEFAULT NULL,
    `target_id` INT UNSIGNED DEFAULT NULL,
    `details` VARCHAR(255) DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_alogs_admin` (`admin_id`),
    KEY `idx_alogs_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `security_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_type` VARCHAR(60) NOT NULL,
    `identifier` VARCHAR(190) DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `details` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_slogs_type` (`event_type`),
    KEY `idx_slogs_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wishlist` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_wishlist_user_item` (`user_id`, `item_id`),
    KEY `idx_wishlist_item` (`item_id`),
    CONSTRAINT `fk_wish_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wish_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reviews` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `rating` TINYINT UNSIGNED NOT NULL,
    `comment` TEXT NOT NULL,
    `status` ENUM('approved', 'pending', 'hidden') NOT NULL DEFAULT 'approved',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_review_user_item` (`user_id`, `item_id`),
    KEY `idx_reviews_item_status` (`item_id`, `status`),
    CONSTRAINT `fk_rev_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rev_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coupons` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(40) NOT NULL,
    `discount_type` ENUM('percent', 'fixed') NOT NULL DEFAULT 'percent',
    `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `min_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `usage_limit` INT UNSIGNED DEFAULT NULL,
    `used_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `per_user_limit` INT UNSIGNED NOT NULL DEFAULT 1,
    `applies_to_all` TINYINT(1) NOT NULL DEFAULT 1,
    `expires_at` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_coupon_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coupon_items` (
    `coupon_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`coupon_id`, `item_id`),
    CONSTRAINT `fk_ci_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ci_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `folder_visits` (
    `user_id` INT UNSIGNED NOT NULL,
    `folder_id` INT UNSIGNED NOT NULL,
    `last_visited_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `folder_id`),
    CONSTRAINT `fk_fv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fv_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `type` VARCHAR(40) NOT NULL,
    `title_bn` VARCHAR(180) NOT NULL,
    `title_en` VARCHAR(180) NOT NULL,
    `body_bn` VARCHAR(255) DEFAULT NULL,
    `body_en` VARCHAR(255) DEFAULT NULL,
    `link_url` VARCHAR(255) DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notif_user_read` (`user_id`, `is_read`, `id`),
    KEY `idx_notif_user_unread` (`user_id`, `is_read`, `id`),
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `subject` VARCHAR(190) DEFAULT NULL,
    `message` TEXT NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `status` ENUM('new', 'read', 'replied') NOT NULL DEFAULT 'new',
    `reply_message` TEXT DEFAULT NULL,
    `replied_by` INT UNSIGNED DEFAULT NULL,
    `replied_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_contact_status_time` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reading_daily_usage` (
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `usage_date` DATE NOT NULL,
    `page_hits` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `item_id`, `usage_date`),
    CONSTRAINT `fk_rdu_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rdu_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `version` VARCHAR(80) NOT NULL,
    `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `slug_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type` ENUM('folder', 'item', 'product', 'guide', 'author', 'hub') NOT NULL,
    `entity_id` INT UNSIGNED NOT NULL,
    `old_path` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_sh_type_path` (`entity_type`, `old_path`),
    KEY `idx_sh_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `guides` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(180) NOT NULL,
    `category_slug` VARCHAR(120) DEFAULT NULL,
    `category_bn` VARCHAR(140) DEFAULT NULL,
    `category_en` VARCHAR(140) DEFAULT NULL,
    `tags` VARCHAR(320) DEFAULT NULL,
    `title_bn` VARCHAR(220) NOT NULL,
    `title_en` VARCHAR(220) NOT NULL,
    `summary_bn` VARCHAR(400) DEFAULT NULL,
    `summary_en` VARCHAR(400) DEFAULT NULL,
    `short_answer_bn` TEXT DEFAULT NULL,
    `short_answer_en` TEXT DEFAULT NULL,
    `body_bn` MEDIUMTEXT DEFAULT NULL,
    `body_en` MEDIUMTEXT DEFAULT NULL,
    `seo_title_bn` VARCHAR(220) DEFAULT NULL,
    `seo_title_en` VARCHAR(220) DEFAULT NULL,
    `seo_description_bn` VARCHAR(320) DEFAULT NULL,
    `seo_description_en` VARCHAR(320) DEFAULT NULL,
    `cover` VARCHAR(255) DEFAULT NULL,
    `author_name` VARCHAR(150) DEFAULT NULL,
    `author_id` INT UNSIGNED DEFAULT NULL,
    `reading_minutes` TINYINT UNSIGNED NOT NULL DEFAULT 4,
    `related_item_ids` VARCHAR(255) DEFAULT NULL,
    `related_folder_ids` VARCHAR(255) DEFAULT NULL,
    `sources_json` MEDIUMTEXT DEFAULT NULL,
    `faqs_json` MEDIUMTEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `publish_at` DATETIME DEFAULT NULL,
    `content_hash` CHAR(64) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_guides_slug` (`slug`),
    KEY `idx_guides_active` (`is_active`, `id`),
    KEY `idx_guides_cat` (`category_slug`, `is_active`),
    KEY `idx_guides_sched` (`is_active`, `publish_at`),
    KEY `idx_guides_upd` (`is_active`, `updated_at`, `id`),
    KEY `idx_guides_active_upd` (`is_active`, `updated_at`, `id`),
    KEY `idx_guides_hash` (`content_hash`),
    FULLTEXT KEY `ft_guides_search` (`title_bn`, `title_en`, `summary_bn`, `summary_en`, `body_bn`, `body_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `authors` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(160) NOT NULL,
    `name_bn` VARCHAR(160) NOT NULL,
    `name_en` VARCHAR(160) NOT NULL,
    `expertise_bn` VARCHAR(220) DEFAULT NULL,
    `expertise_en` VARCHAR(220) DEFAULT NULL,
    `bio_bn` TEXT DEFAULT NULL,
    `bio_en` TEXT DEFAULT NULL,
    `photo` VARCHAR(255) DEFAULT NULL,
    `social_facebook` VARCHAR(255) DEFAULT NULL,
    `social_linkedin` VARCHAR(255) DEFAULT NULL,
    `social_website` VARCHAR(255) DEFAULT NULL,
    `seo_title_bn` VARCHAR(220) DEFAULT NULL,
    `seo_title_en` VARCHAR(220) DEFAULT NULL,
    `seo_description_bn` VARCHAR(320) DEFAULT NULL,
    `seo_description_en` VARCHAR(320) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_authors_slug` (`slug`),
    KEY `idx_authors_active` (`is_active`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hubs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(160) NOT NULL,
    `title_bn` VARCHAR(220) NOT NULL,
    `title_en` VARCHAR(220) NOT NULL,
    `short_answer_bn` TEXT DEFAULT NULL,
    `short_answer_en` TEXT DEFAULT NULL,
    `intro_bn` MEDIUMTEXT DEFAULT NULL,
    `intro_en` MEDIUMTEXT DEFAULT NULL,
    `guide_id` INT UNSIGNED DEFAULT NULL,
    `featured_item_ids` VARCHAR(255) DEFAULT NULL,
    `featured_folder_ids` VARCHAR(255) DEFAULT NULL,
    `faqs_json` MEDIUMTEXT DEFAULT NULL,
    `seo_title_bn` VARCHAR(220) DEFAULT NULL,
    `seo_title_en` VARCHAR(220) DEFAULT NULL,
    `seo_description_bn` VARCHAR(320) DEFAULT NULL,
    `seo_description_en` VARCHAR(320) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_hubs_slug` (`slug`),
    KEY `idx_hubs_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `editorial_faqs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scope_type` ENUM('global', 'guide', 'folder', 'hub') NOT NULL DEFAULT 'global',
    `scope_id` INT UNSIGNED DEFAULT NULL,
    `topic` VARCHAR(80) NOT NULL DEFAULT 'general',
    `question_bn` VARCHAR(300) NOT NULL,
    `answer_bn` TEXT NOT NULL,
    `question_en` VARCHAR(300) NOT NULL,
    `answer_en` TEXT NOT NULL,
    `link_url` VARCHAR(255) DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ed_faq_scope` (`scope_type`, `scope_id`, `is_active`, `sort_order`),
    KEY `idx_ed_faq_topic` (`topic`, `is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `seo_redirects` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `from_path` VARCHAR(255) NOT NULL,
    `to_path` VARCHAR(255) DEFAULT NULL,
    `status_code` SMALLINT UNSIGNED NOT NULL DEFAULT 301,
    `note` VARCHAR(255) DEFAULT NULL,
    `hits` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_seo_redir_from` (`from_path`),
    KEY `idx_seo_redir_code` (`status_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `seo_404_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `path` VARCHAR(255) NOT NULL,
    `referrer` VARCHAR(255) DEFAULT NULL,
    `hits` INT UNSIGNED NOT NULL DEFAULT 1,
    `last_seen_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_seo_404_path` (`path`),
    KEY `idx_seo_404_hits` (`hits`, `last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `indexnow_queue` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `url` VARCHAR(500) NOT NULL,
    `url_hash` CHAR(40) NOT NULL,
    `entity_type` VARCHAR(40) NOT NULL DEFAULT 'book',
    `event_type` VARCHAR(24) NOT NULL DEFAULT 'update',
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_idxnow_url_hash` (`url_hash`),
    KEY `idx_idxnow_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `indexnow_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `engine` VARCHAR(80) NOT NULL DEFAULT 'api.indexnow.org',
    `action_type` VARCHAR(32) NOT NULL DEFAULT 'url_batch',
    `url_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `urls_json` MEDIUMTEXT DEFAULT NULL,
    `http_status` SMALLINT NOT NULL DEFAULT 0,
    `response_excerpt` VARCHAR(500) DEFAULT NULL,
    `status` ENUM('sent','queued','failed','rate_limited') NOT NULL DEFAULT 'sent',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_idxnow_logs_created` (`created_at`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `analytics_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_type` VARCHAR(24) NOT NULL DEFAULT 'pageview',
    `path` VARCHAR(255) NOT NULL,
    `lang` CHAR(2) NOT NULL DEFAULT 'bn',
    `referrer_host` VARCHAR(160) DEFAULT NULL,
    `search_query` VARCHAR(180) DEFAULT NULL,
    `search_results_count` INT DEFAULT NULL,
    `device_class` ENUM('mobile','tablet','desktop') NOT NULL DEFAULT 'desktop',
    `country_code` VARCHAR(12) NOT NULL DEFAULT 'ZZ',
    `load_ms` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ae_created_type` (`created_at`, `event_type`),
    KEY `idx_ae_path_created` (`path`, `created_at`),
    KEY `idx_ae_type_lang` (`event_type`, `lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `seo_link_checks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pair_hash` CHAR(40) NOT NULL,
    `source_path` VARCHAR(255) NOT NULL,
    `target_path` VARCHAR(500) NOT NULL,
    `http_status` SMALLINT NOT NULL DEFAULT 0,
    `is_broken` TINYINT(1) NOT NULL DEFAULT 0,
    `is_redirect_chain` TINYINT(1) NOT NULL DEFAULT 0,
    `redirect_hops` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `final_url` VARCHAR(500) DEFAULT NULL,
    `checked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_link_pair_hash` (`pair_hash`),
    KEY `idx_link_broken` (`is_broken`, `checked_at`),
    KEY `idx_link_chain` (`is_redirect_chain`, `checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(190) NOT NULL,
    `lang` CHAR(2) NOT NULL DEFAULT 'bn',
    `status` ENUM('pending','active','unsubscribed') NOT NULL DEFAULT 'pending',
    `verify_token_hash` CHAR(64) DEFAULT NULL,
    `unsubscribe_token` CHAR(32) NOT NULL,
    `source_path` VARCHAR(255) DEFAULT '/',
    `verified_at` DATETIME DEFAULT NULL,
    `unsubscribed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_news_email` (`email`),
    KEY `idx_news_status` (`status`, `created_at`),
    KEY `idx_news_vhash` (`verify_token_hash`),
    KEY `idx_news_unsub` (`unsubscribe_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `folder_subscriptions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `folder_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_folder_sub` (`user_id`, `folder_id`),
    KEY `idx_folder_sub_fid` (`folder_id`),
    CONSTRAINT `fk_fsub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fsub_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `seo_alerts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `alert_type` VARCHAR(40) NOT NULL,
    `severity` ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
    `title_bn` VARCHAR(220) NOT NULL,
    `title_en` VARCHAR(220) NOT NULL,
    `detail` VARCHAR(600) DEFAULT NULL,
    `is_resolved` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_seo_alerts_open` (`is_resolved`, `alert_type`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shop_categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `slug` VARCHAR(160) NOT NULL,
    `name_bn` VARCHAR(190) NOT NULL,
    `name_en` VARCHAR(190) NOT NULL,
    `description_bn` TEXT DEFAULT NULL,
    `description_en` TEXT DEFAULT NULL,
    `thumbnail` VARCHAR(255) DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_sc_slug` (`slug`),
    KEY `idx_sc_parent_active` (`parent_id`, `is_active`, `sort_order`),
    CONSTRAINT `fk_shop_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `shop_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `slug` VARCHAR(190) NOT NULL,
    `sku` VARCHAR(80) DEFAULT NULL,
    `title_bn` VARCHAR(220) NOT NULL,
    `title_en` VARCHAR(220) NOT NULL,
    `author_bn` VARCHAR(160) DEFAULT NULL,
    `author_en` VARCHAR(160) DEFAULT NULL,
    `publisher` VARCHAR(160) DEFAULT NULL,
    `isbn` VARCHAR(40) DEFAULT NULL,
    `cover_image` VARCHAR(255) DEFAULT NULL,
    `description_bn` MEDIUMTEXT DEFAULT NULL,
    `description_en` MEDIUMTEXT DEFAULT NULL,
    `seo_title_bn` VARCHAR(220) DEFAULT NULL,
    `seo_title_en` VARCHAR(220) DEFAULT NULL,
    `seo_description_bn` VARCHAR(320) DEFAULT NULL,
    `seo_description_en` VARCHAR(320) DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_price` DECIMAL(10,2) DEFAULT NULL,
    `stock` INT NOT NULL DEFAULT 0,
    `low_stock_threshold` INT UNSIGNED NOT NULL DEFAULT 5,
    `weight_grams` INT UNSIGNED NOT NULL DEFAULT 300,
    `sales_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_prod_slug` (`slug`),
    UNIQUE KEY `uniq_prod_sku` (`sku`),
    KEY `idx_prod_cat_active` (`category_id`, `is_active`),
    KEY `idx_prod_featured` (`is_featured`, `is_active`),
    KEY `idx_prod_active_feat` (`is_active`, `is_featured`, `id`),
    KEY `idx_prod_stock` (`is_active`, `stock`),
    FULLTEXT KEY `ft_products_search` (`title_bn`, `title_en`, `author_bn`, `author_en`, `publisher`, `isbn`, `description_bn`, `description_en`),
    CONSTRAINT `fk_shop_prod_cat` FOREIGN KEY (`category_id`) REFERENCES `shop_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_images` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_cover` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pi_prod` (`product_id`, `is_cover`, `sort_order`),
    CONSTRAINT `fk_shop_pi_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_stock_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `admin_id` INT UNSIGNED DEFAULT NULL,
    `change_qty` INT NOT NULL,
    `stock_before` INT NOT NULL,
    `stock_after` INT NOT NULL,
    `reason` VARCHAR(190) NOT NULL,
    `ref_order_id` BIGINT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_psl_prod` (`product_id`, `created_at`),
    CONSTRAINT `fk_shop_psl_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_shop_psl_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_wishlist` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_pw_user_prod` (`user_id`, `product_id`),
    KEY `idx_pw_user` (`user_id`),
    CONSTRAINT `fk_shop_pw_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_shop_pw_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_reviews` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `shop_order_id` BIGINT UNSIGNED DEFAULT NULL,
    `rating` TINYINT UNSIGNED NOT NULL,
    `comment` TEXT DEFAULT NULL,
    `status` ENUM('approved', 'pending', 'hidden') NOT NULL DEFAULT 'approved',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_pr_user_prod` (`user_id`, `product_id`),
    KEY `idx_pr_prod_status` (`product_id`, `status`),
    CONSTRAINT `fk_shop_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_shop_pr_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shop_carts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cart_token` CHAR(64) DEFAULT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `coupon_code` VARCHAR(60) DEFAULT NULL,
    `delivery_area` ENUM('inside_dhaka', 'outside_dhaka') NOT NULL DEFAULT 'inside_dhaka',
    `district` VARCHAR(80) NOT NULL DEFAULT 'Dhaka',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_sc_token` (`cart_token`),
    UNIQUE KEY `uniq_sc_user` (`user_id`),
    CONSTRAINT `fk_shop_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shop_cart_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cart_id` BIGINT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_sci_cart_prod` (`cart_id`, `product_id`),
    CONSTRAINT `fk_shop_ci_cart` FOREIGN KEY (`cart_id`) REFERENCES `shop_carts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_shop_ci_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shop_orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_code` VARCHAR(40) NOT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `customer_name` VARCHAR(120) NOT NULL,
    `customer_email` VARCHAR(190) DEFAULT NULL,
    `customer_phone` VARCHAR(30) NOT NULL,
    `shipping_address` TEXT NOT NULL,
    `district` VARCHAR(80) NOT NULL DEFAULT 'Dhaka',
    `delivery_area` ENUM('inside_dhaka', 'outside_dhaka') NOT NULL DEFAULT 'inside_dhaka',
    `subtotal_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `coupon_id` INT UNSIGNED DEFAULT NULL,
    `coupon_code` VARCHAR(60) DEFAULT NULL,
    `coupon_decremented` TINYINT(1) NOT NULL DEFAULT 0,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_method` VARCHAR(30) NOT NULL DEFAULT 'cod',
    `gateway_driver` VARCHAR(40) DEFAULT NULL,
    `gateway_ref` VARCHAR(140) DEFAULT NULL,
    `sender_number` VARCHAR(40) DEFAULT NULL,
    `transaction_id` VARCHAR(80) DEFAULT NULL,
    `payment_screenshot` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending', 'confirmed', 'packed', 'shipped', 'delivered', 'cancelled', 'returned') NOT NULL DEFAULT 'pending',
    `stock_restored` TINYINT(1) NOT NULL DEFAULT 0,
    `courier_name` VARCHAR(100) DEFAULT NULL,
    `tracking_code` VARCHAR(120) DEFAULT NULL,
    `admin_note` TEXT DEFAULT NULL,
    `internal_notes` TEXT DEFAULT NULL,
    `return_status` ENUM('none', 'requested', 'approved', 'rejected') NOT NULL DEFAULT 'none',
    `return_reason` TEXT DEFAULT NULL,
    `return_note` TEXT DEFAULT NULL,
    `return_requested_at` DATETIME DEFAULT NULL,
    `delivered_at` DATETIME DEFAULT NULL,
    `reviewed_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_so_code` (`order_code`),
    UNIQUE KEY `uniq_shop_method_trx` (`payment_method`, `transaction_id`),
    KEY `idx_so_status` (`status`, `created_at`),
    KEY `idx_so_user` (`user_id`, `status`),
    KEY `idx_so_phone` (`customer_phone`),
    KEY `idx_so_trx` (`transaction_id`),
    KEY `idx_so_return` (`return_status`),
    CONSTRAINT `fk_shop_ord_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_shop_ord_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shop_order_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `shop_order_id` BIGINT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED DEFAULT NULL,
    `title_bn` VARCHAR(220) NOT NULL,
    `title_en` VARCHAR(220) NOT NULL,
    `sku` VARCHAR(80) DEFAULT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `line_total` DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_soi_order` (`shop_order_id`),
    KEY `idx_soi_prod` (`product_id`),
    CONSTRAINT `fk_shop_oi_order` FOREIGN KEY (`shop_order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_shop_oi_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DEFAULT SYSTEM SETTINGS & CORE LANGUAGE STRINGS (Production Safe)
-- Zero demo accounts or sample passwords are included here.
-- Use /install on first deployment to create your Super Admin account.
-- ============================================================================
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'Bookexa'),
('site_logo', 'assets/img/logo.svg'),
('site_favicon', 'assets/img/favicon.svg'),
('default_language', 'bn'),
('maintenance_mode', '0'),
('shop_enabled', '0'),
('shop_cod_enabled', '1'),
('shop_delivery_dhaka', '60'),
('shop_delivery_outside', '120'),
('shop_delivery_district_json', '{}'),
('shop_free_delivery_above', '1500'),
('shop_est_days_dhaka', '1-2 days'),
('shop_est_days_outside', '3-5 days'),
('shop_return_days', '7'),
('free_pdfjs_fallback', '1'),
('contact_phone', '+880 1700-000001'),
('contact_email', 'support@bookexa.com'),
('contact_address', 'Dhaka, Bangladesh'),
('bkash_number', '01711-000111 (Personal - Send Money)'),
('nagad_number', '01811-000222 (Personal - Send Money)'),
('rocket_number', '01911-000333-4 (Personal)'),
('payment_instructions_bn', 'উপরের যেকোনো নম্বরে Send Money করে নিচে আপনার প্রেরক নম্বর ও Transaction ID (TrxID) জমা দিন। অ্যাডমিন যাচাই করার সাথে সাথেই বইটি আনলক হবে।'),
('payment_instructions_en', 'Send Money to any number above, then submit your sender number and Transaction ID (TrxID). Once Admin approves, the PDF unlocks in My Library.'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_from_name', 'Bookexa'),
('smtp_from_email', 'no-reply@bookexa.com'),
('smtp_encryption', 'tls'),
('sms_daily_budget', '200'),
('theme_primary_color', '#2B2F77'),
('theme_accent_color', '#F4A62A'),
('theme_font_pair', 'hind_noto'),
('footer_text_bn', ''),
('footer_text_en', ''),
('social_facebook', ''),
('social_youtube', ''),
('social_telegram', ''),
('social_whatsapp', ''),
('social_instagram', ''),
('demo_mode', '0'),
('seo_google_verify', ''),
('seo_bing_verify', ''),
('seo_default_og_image', 'assets/img/apple-touch-icon.png'),
('seo_default_region', 'BD'),
('site_founding_year', '2024'),
('seo_sameas_extra', ''),
('legal_last_reviewed', '2026-11-26'),
('aeo_send_noai_paid', '1'),
('aeo_global_noimageai', '0'),
('debug_timing', '0'),
('indexnow_enabled', '1'),
('indexnow_last_ping_at', '0'),
('ga4_measurement_id', ''),
('clarity_project_id', ''),
('gsc_verify_file_name', ''),
('gsc_verify_file_content', ''),
('analytics_dnt_honor', '1'),
('cookie_banner_enabled', '1'),
('robots_last_hash', ''),
('seo_tpl_home_title_bn', '{site} — নিরাপদ ডিজিটাল বই ও একাডেমিক পিডিএফ'),
('seo_tpl_home_title_en', '{site} — Digital Books & Academic PDF Library'),
('seo_tpl_home_desc_bn', 'বিসিএস, ভর্তি, এসএসসি ও এইচএসসি পিডিএফ বই ও স্টাডি গাইড নিরাপদে পড়ুন {site}-এ।'),
('seo_tpl_home_desc_en', 'Read protected academic PDFs, BCS prep books, and order printed books online across Bangladesh on {site}.'),
('seo_tpl_folder_title_bn', '{title} — পিডিএফ বই ও নোট | {site}'),
('seo_tpl_folder_title_en', '{title} — Academic PDFs & Notes | {site}'),
('seo_tpl_folder_desc_bn', '{title} ক্যাটাগরির সেরা একাডেমিক পিডিএফ বই ও স্টাডি নোট নিরাপদে পড়ুন ও সংগ্রহ করুন {site}-এ।'),
('seo_tpl_folder_desc_en', 'Explore {title} study PDFs, notes, and guides. Preview sample pages securely online on {site}.'),
('seo_tpl_book_title_bn', '{title} — {author} | {site}'),
('seo_tpl_book_title_en', '{title} by {author} | {site}'),
('seo_tpl_book_desc_bn', '{title} ({author}) — {folder}। মূল্য {price}। ফ্রি স্যাম্পল পেজ পড়ুন ও আনলক করুন {site}-এ।'),
('seo_tpl_book_desc_en', 'Read {title} by {author} in {folder} ({price}). Preview sample pages securely online on {site}.'),
('seo_tpl_shop_product_title_bn', '{title} — {price} | {site}'),
('seo_tpl_shop_product_title_en', '{title} ({price}) | {site}'),
('seo_tpl_shop_product_desc_bn', '{title} ({author}) প্রিন্টেড বই অর্ডার করুন {price}-এ। সারা বাংলাদেশে দ্রুত হোম ডেলিভারি {site}-এ।'),
('seo_tpl_shop_product_desc_en', 'Order printed copy of {title} by {author} for {price} with fast home delivery across Bangladesh on {site}.'),
('seo_tpl_guide_title_bn', '{title} — স্টাডি গাইড | {site}'),
('seo_tpl_guide_title_en', '{title} — Study Guide | {site}'),
('seo_tpl_guide_desc_bn', '{title} সম্পর্কে বিস্তারিত প্রস্তুতি কৌশল, স্টাডি গাইড ও প্রয়োজনীয় বইয়ের তালিকা পড়ুন {site}-এ।'),
('seo_tpl_guide_desc_en', 'Read {title}: detailed preparation guide, curated reading tips, and study resources on {site}.'),
('seo_tpl_author_title_bn', '{author} — সকল বই ও প্রকাশনা | {site}'),
('seo_tpl_author_title_en', '{author} — Books & Publications | {site}'),
('seo_tpl_author_desc_bn', '{author}-এর সকল একাডেমিক পিডিএফ ও প্রিন্টেড বইয়ের সম্পূর্ণ তালিকা দেখুন ও পড়ুন {site}-এ।'),
('seo_tpl_author_desc_en', 'Browse all digital PDFs and printed books written by {author} available on {site}.'),
('seo_tpl_search_title_bn', 'খুঁজুন: {title} | {site}'),
('seo_tpl_search_title_en', 'Search: {title} | {site}'),
('seo_tpl_search_desc_bn', '{site}-এ একাডেমিক পিডিএফ বই, লেখক ও স্টাডি গাইড মুহূর্তেই খুঁজে নিন এবং অনলাইনে নিরাপদে পড়া শুরু করুন।'),
('seo_tpl_search_desc_en', 'Search academic PDFs, books, authors, and study notes quickly on {site} across Bangladesh.'),
('seo_tpl_static_title_bn', '{title} | {site}'),
('seo_tpl_static_title_en', '{title} | {site}'),
('seo_tpl_static_desc_bn', '{title} সম্পর্কিত বিস্তারিত তথ্য, নীতিমালা এবং পাঠক সহায়তা নির্দেশিকা দেখুন {site} প্ল্যাটফর্মে।'),
('seo_tpl_static_desc_en', 'Read important information, platform policies, and reader support details for {title} on {site}.'),
('legal_terms_bn', '<h2>ব্যবহারের শর্তাবলি</h2><p>Bookexa-তে নিবন্ধন ও ডিজিটাল বই ব্যবহারের মাধ্যমে আপনি এই শর্তাবলিতে সম্মত হচ্ছেন। আপনার অ্যাকাউন্ট ব্যক্তিগত ব্যবহারের জন্য—লগইন তথ্য বা ক্রয়কৃত কনটেন্ট অন্যের সাথে শেয়ার করা নিষিদ্ধ।</p><p>প্ল্যাটফর্মের সকল পিডিএফ শুধুমাত্র সুরক্ষিত ইন-ব্রাউজার রিডারে পড়ার জন্য সরবরাহ করা হয়।</p>'),
('legal_terms_en', '<h2>Terms of Use</h2><p>By registering and reading digital books on Bookexa, you agree to use your account strictly for personal study. Sharing account credentials or redistributing purchased content is prohibited.</p><p>All PDFs are licensed for viewing inside Bookexa\'s protected in-browser reader only.</p>'),
('legal_privacy_bn', '<h2>গোপনীয়তা নীতি</h2><p>আমরা কেবল অ্যাকাউন্ট তৈরি, অর্ডার যাচাই, রিডিং সেশন সুরক্ষা ও প্রয়োজনীয় নোটিফিকেশনের জন্য আপনার নাম, ইমেইল, ফোন নম্বর ও রিডিং অগ্রগতি সংরক্ষণ করি।</p><p>ব্যবহারকারীর ব্যক্তিগত তথ্য তৃতীয় পক্ষের কাছে বিক্রি বা হস্তান্তর করা হয় না।</p>'),
('legal_privacy_en', '<h2>Privacy Policy</h2><p>We collect your name, email, phone number, and reading progress solely to provide library access, verify manual payments, secure single-session reading, and send essential account notifications.</p><p>We never sell personal data to third parties.</p>'),
('legal_refund_bn', '<h2>রিফান্ড নীতি</h2><p>ডিজিটাল পিডিএফ আনলক হয়ে যাওয়ার পর সাধারণত অর্থ ফেরতযোগ্য নয়। তবে ভুলবশত ডাবল পেমেন্ট হলে বা অর্ডার বাতিল হলে ৪৮ ঘণ্টার মধ্যে সাপোর্টে যোগাযোগ করলে যাচাই সাপেক্ষে রিফান্ড করা হবে।</p>'),
('legal_refund_en', '<h2>Refund Policy</h2><p>Digital PDFs are non-refundable once unlocked in My Library. For duplicate transactions or rejected payments, contact support within 48 hours for verification and refund.</p>'),
('legal_copyright_bn', '<h2>কপিরাইট ও ওয়াটারমার্ক বিজ্ঞপ্তি</h2><p>Bookexa রিডারের প্রতিটি পৃষ্ঠায় পাঠকের নাম, ফোন নম্বর/ইমেইল, ইউজার আইডি ও সময় সংবলিত ডায়নামিক ওয়াটারমার্ক যুক্ত থাকে। কোনো বই বা পৃষ্ঠার স্ক্রিনশট, কপি, প্রিন্ট বা অননুমোদিত শেয়ার করা কপিরাইট আইনে দণ্ডনীয় এবং অ্যাকাউন্ট স্থায়ীভাবে বন্ধ হতে পারে।</p>'),
('legal_copyright_en', '<h2>Copyright &amp; Watermark Notice</h2><p>Every page rendered in Bookexa Reader carries a dynamic forensic watermark containing your name, email/phone, user ID, and timestamp. Reproducing, recording, distributing, or sharing any page is strictly prohibited and may result in account termination and legal action.</p>')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

INSERT INTO `language_strings` (`lang_key`, `text_bn`, `text_en`) VALUES
('site_name', 'Bookexa', 'Bookexa'),
('tagline', 'নিরাপদ ডিজিটাল বই ও একাডেমিক পিডিএফ ভুবন', 'Secure Digital Books & Study PDF Platform'),
('nav_home', 'হোম', 'Home'),
('nav_pdfs', 'পিডিএফ সংগ্রহ', 'Browse PDFs'),
('nav_library', 'আমার লাইব্রেরি', 'My Library'),
('nav_login', 'লগইন', 'Login')
ON DUPLICATE KEY UPDATE `text_bn` = VALUES(`text_bn`);

SET FOREIGN_KEY_CHECKS = 1;
