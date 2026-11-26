<?php
declare(strict_types=1);

return [
    'description' => 'Contact messages, unique TrxID, coupon guards, atomic page staging & reader quota tables',
    'up' => static function (PDO $pdo): void {
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

        mig_create_table_if_missing(
            $pdo,
            'reading_daily_usage',
            "CREATE TABLE IF NOT EXISTS `reading_daily_usage` (
                `user_id` INT UNSIGNED NOT NULL,
                `item_id` INT UNSIGNED NOT NULL,
                `usage_date` DATE NOT NULL,
                `page_hits` INT UNSIGNED NOT NULL DEFAULT 0,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`user_id`, `item_id`, `usage_date`),
                CONSTRAINT `fk_rdu_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_rdu_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

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
