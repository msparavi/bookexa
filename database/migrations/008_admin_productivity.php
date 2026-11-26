<?php
declare(strict_types=1);

return [
    'description' => 'Admin productivity features (RBAC, 2FA/TOTP, sessions, inbox reply, scheduled publishing, search analytics)',
    'up' => static function (PDO $pdo): void {
        mig_modify_column_if_exists($pdo, 'admins', 'role', "ENUM('superadmin', 'editor', 'support', 'analyst') NOT NULL DEFAULT 'superadmin'");
        mig_add_column_if_missing($pdo, 'admins', 'totp_secret', 'VARCHAR(255) DEFAULT NULL AFTER `must_change_password`');
        mig_add_column_if_missing($pdo, 'admins', 'totp_enabled', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `totp_secret`');
        mig_add_column_if_missing($pdo, 'admins', 'backup_codes_json', 'TEXT DEFAULT NULL AFTER `totp_enabled`');

        mig_create_table_if_missing(
            $pdo,
            'admin_sessions',
            "CREATE TABLE IF NOT EXISTS `admin_sessions` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        mig_add_column_if_missing($pdo, 'contact_messages', 'reply_message', 'TEXT DEFAULT NULL AFTER `status`');
        mig_add_column_if_missing($pdo, 'contact_messages', 'replied_by', 'INT UNSIGNED DEFAULT NULL AFTER `reply_message`');
        mig_add_column_if_missing($pdo, 'contact_messages', 'replied_at', 'DATETIME DEFAULT NULL AFTER `replied_by`');

        mig_add_column_if_missing($pdo, 'banners', 'publish_at', 'DATETIME DEFAULT NULL AFTER `ends_at`');
        mig_add_column_if_missing($pdo, 'banners', 'unpublish_at', 'DATETIME DEFAULT NULL AFTER `publish_at`');

        mig_add_column_if_missing($pdo, 'folders', 'publish_at', 'DATETIME DEFAULT NULL AFTER `is_active`');
        mig_add_column_if_missing($pdo, 'folders', 'unpublish_at', 'DATETIME DEFAULT NULL AFTER `publish_at`');

        mig_add_column_if_missing($pdo, 'items', 'publish_at', 'DATETIME DEFAULT NULL AFTER `is_active`');
        mig_add_column_if_missing($pdo, 'items', 'unpublish_at', 'DATETIME DEFAULT NULL AFTER `publish_at`');

        mig_create_table_if_missing(
            $pdo,
            'search_queries',
            "CREATE TABLE IF NOT EXISTS `search_queries` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `query_text` VARCHAR(190) NOT NULL,
                `lang` VARCHAR(5) NOT NULL DEFAULT 'bn',
                `hits` INT UNSIGNED NOT NULL DEFAULT 1,
                `last_searched_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_sq_query` (`query_text`),
                KEY `idx_sq_hits` (`hits`, `last_searched_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        foreach ([
            'admin_ip_allowlist'    => '',
            'last_cron_at'          => '',
            'last_daily_backup_at'  => '',
        ] as $k => $v) {
            mig_insert_setting_if_missing($pdo, $k, $v);
        }
    },
];
