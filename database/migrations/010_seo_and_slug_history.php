<?php
declare(strict_types=1);

return [
    'description' => 'SEO metadata fields on folders/items and slug_history table for 301 redirects',
    'up' => static function (PDO $pdo): void {
        mig_add_column_if_missing($pdo, 'folders', 'seo_title_bn', 'VARCHAR(220) DEFAULT NULL AFTER `description_en`');
        mig_add_column_if_missing($pdo, 'folders', 'seo_title_en', 'VARCHAR(220) DEFAULT NULL AFTER `seo_title_bn`');
        mig_add_column_if_missing($pdo, 'folders', 'seo_description_bn', 'VARCHAR(320) DEFAULT NULL AFTER `seo_title_en`');
        mig_add_column_if_missing($pdo, 'folders', 'seo_description_en', 'VARCHAR(320) DEFAULT NULL AFTER `seo_description_bn`');

        mig_add_column_if_missing($pdo, 'items', 'seo_title_bn', 'VARCHAR(220) DEFAULT NULL AFTER `description_en`');
        mig_add_column_if_missing($pdo, 'items', 'seo_title_en', 'VARCHAR(220) DEFAULT NULL AFTER `seo_title_bn`');
        mig_add_column_if_missing($pdo, 'items', 'seo_description_bn', 'VARCHAR(320) DEFAULT NULL AFTER `seo_title_en`');
        mig_add_column_if_missing($pdo, 'items', 'seo_description_en', 'VARCHAR(320) DEFAULT NULL AFTER `seo_description_bn`');

        mig_create_table_if_missing(
            $pdo,
            'slug_history',
            "CREATE TABLE IF NOT EXISTS `slug_history` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `entity_type` ENUM('folder', 'item') NOT NULL,
                `entity_id` INT UNSIGNED NOT NULL,
                `old_path` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_sh_type_path` (`entity_type`, `old_path`),
                KEY `idx_sh_entity` (`entity_type`, `entity_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    },
];
