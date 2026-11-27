<?php
declare(strict_types=1);

return [
    'description' => 'Contact messages, unique TrxID, coupon guards, atomic page staging & reader quota tables',
    'up' => static function (PDO $pdo): void {
        // 1. Ensure parent tables exist with InnoDB BEFORE any foreign keys reference them
        mig_create_table_if_missing(
            $pdo,
            'users',
            "CREATE TABLE IF NOT EXISTS `users` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        mig_create_table_if_missing(
            $pdo,
            'folders',
            "CREATE TABLE IF NOT EXISTS `folders` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `parent_id` INT UNSIGNED DEFAULT NULL,
                `slug` VARCHAR(140) NOT NULL,
                `name_bn` VARCHAR(180) NOT NULL,
                `name_en` VARCHAR(180) NOT NULL,
                `description_bn` TEXT DEFAULT NULL,
                `description_en` TEXT DEFAULT NULL,
                `thumbnail` VARCHAR(255) DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_folders_slug` (`slug`),
                KEY `idx_folders_parent` (`parent_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        mig_create_table_if_missing(
            $pdo,
            'items',
            "CREATE TABLE IF NOT EXISTS `items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `folder_id` INT UNSIGNED DEFAULT NULL,
                `slug` VARCHAR(160) NOT NULL,
                `title_bn` VARCHAR(220) NOT NULL,
                `title_en` VARCHAR(220) NOT NULL,
                `author_bn` VARCHAR(150) DEFAULT NULL,
                `author_en` VARCHAR(150) DEFAULT NULL,
                `description_bn` TEXT DEFAULT NULL,
                `description_en` TEXT DEFAULT NULL,
                `cover` VARCHAR(255) DEFAULT NULL,
                `drive_link` TEXT DEFAULT NULL,
                `sample_drive_link` TEXT DEFAULT NULL,
                `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `original_price` DECIMAL(10,2) DEFAULT NULL,
                `is_free` TINYINT(1) NOT NULL DEFAULT 0,
                `sample_pages` INT UNSIGNED NOT NULL DEFAULT 4,
                `total_pages` INT UNSIGNED DEFAULT NULL,
                `pages_status` ENUM('unprocessed', 'processing', 'ready') NOT NULL DEFAULT 'unprocessed',
                `processing_status` ENUM('idle', 'processing', 'failed') NOT NULL DEFAULT 'idle',
                `processed_at` DATETIME DEFAULT NULL,
                `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_items_slug` (`slug`),
                KEY `idx_items_folder_id` (`folder_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        mig_ensure_innodb($pdo, 'users');
        mig_ensure_innodb($pdo, 'items');

        mig_create_table_if_missing(
            $pdo,
            'contact_messages',
            "CREATE TABLE IF NOT EXISTS `contact_messages` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(120) NOT NULL,
                `email` VARCHAR(190) NOT NULL,
                `phone` VARCHAR(30) DEFAULT NULL,
                `subject` VARCHAR(190) DEFAULT NULL,
                `message` TEXT NOT NULL,
                `ip` VARCHAR(45) NOT NULL,
                `status` ENUM('new', 'read', 'replied') NOT NULL DEFAULT 'new',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_contact_status_time` (`status`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // 2. Match reading_daily_usage FK column types & signedness to users.id / items.id
        $userIdType = mig_column_type($pdo, 'users', 'id') ?? 'INT UNSIGNED';
        $itemIdType = mig_column_type($pdo, 'items', 'id') ?? 'INT UNSIGNED';

        mig_create_table_if_missing(
            $pdo,
            'reading_daily_usage',
            "CREATE TABLE IF NOT EXISTS `reading_daily_usage` (
                `user_id` {$userIdType} NOT NULL,
                `item_id` {$itemIdType} NOT NULL,
                `usage_date` DATE NOT NULL,
                `page_hits` INT UNSIGNED NOT NULL DEFAULT 0,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`user_id`, `item_id`, `usage_date`),
                KEY `idx_rdu_item` (`item_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        mig_add_index_if_missing($pdo, 'reading_daily_usage', 'idx_rdu_item', 'KEY `idx_rdu_item` (`item_id`)');
        mig_add_foreign_key_if_missing($pdo, 'reading_daily_usage', 'fk_rdu_user', 'user_id', 'users', 'id', 'CASCADE');
        mig_add_foreign_key_if_missing($pdo, 'reading_daily_usage', 'fk_rdu_item', 'item_id', 'items', 'id', 'CASCADE');

        mig_add_column_if_missing($pdo, 'items', 'processing_status', "ENUM('idle', 'processing', 'failed') NOT NULL DEFAULT 'idle' AFTER `pages_status`");
        mig_add_column_if_missing($pdo, 'users', 'reading_suspended_until', 'DATETIME DEFAULT NULL AFTER `reader_zoom`');
        mig_add_column_if_missing($pdo, 'orders', 'coupon_decremented', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `coupon_code`');

        if (mig_table_exists($pdo, 'orders') && !mig_index_exists($pdo, 'orders', 'uniq_orders_method_trx')) {
            $pdo->exec(
                "UPDATE `orders`
                 SET `transaction_id` = NULL
                 WHERE `transaction_id` IS NULL
                    OR TRIM(`transaction_id`) = ''
                    OR UPPER(TRIM(`transaction_id`)) LIKE 'COUPON%'"
            );
            $pdo->exec(
                "UPDATE `orders`
                 SET `transaction_id` = UPPER(TRIM(`transaction_id`))
                 WHERE `transaction_id` IS NOT NULL"
            );
            $pdo->exec(
                "UPDATE `orders` o1
                 JOIN `orders` o2
                   ON o1.payment_method = o2.payment_method
                  AND o1.transaction_id = o2.transaction_id
                  AND o1.id > o2.id
                 SET o1.transaction_id = CONCAT(o1.transaction_id, '-DUP', o1.id)"
            );
        }

        mig_add_index_if_missing($pdo, 'orders', 'uniq_orders_method_trx', 'UNIQUE KEY `uniq_orders_method_trx` (`payment_method`, `transaction_id`)');
        mig_add_index_if_missing($pdo, 'orders', 'idx_orders_sender', 'KEY `idx_orders_sender` (`sender_number`)');
        mig_insert_setting_if_missing($pdo, 'reader_daily_quota_mult', '3');
    },
];
