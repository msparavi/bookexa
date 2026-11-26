<?php
declare(strict_types=1);

return [
    'description' => 'IndexNow queue/logs, privacy-friendly analytics, link checker, SEO alerts, newsletter double opt-in & folder subscriptions',
    'up' => static function (PDO $pdo): void {
        mig_create_table_if_missing($pdo, 'indexnow_queue', "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        mig_create_table_if_missing($pdo, 'indexnow_logs', "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        mig_create_table_if_missing($pdo, 'analytics_events', "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        mig_create_table_if_missing($pdo, 'seo_link_checks', "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        mig_create_table_if_missing($pdo, 'newsletter_subscribers', "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        mig_create_table_if_missing($pdo, 'folder_subscriptions', "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        mig_create_table_if_missing($pdo, 'seo_alerts', "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $defaultKey = bin2hex(random_bytes(16));
        $defaults = [
            'indexnow_key'            => $defaultKey,
            'indexnow_enabled'        => '1',
            'indexnow_last_ping_at'   => '0',
            'ga4_measurement_id'      => '',
            'clarity_project_id'      => '',
            'gsc_verify_file_name'    => '',
            'gsc_verify_file_content' => '',
            'analytics_dnt_honor'     => '1',
            'cookie_banner_enabled'   => '1',
            'robots_last_hash'        => '',
        ];
        foreach ($defaults as $k => $v) {
            mig_insert_setting_if_missing($pdo, $k, $v);
        }
    },
];
