-- Bookexa Migration 010: SEO Metadata Fields & Slug History for 301 Redirects

ALTER TABLE `folders`
    ADD COLUMN `seo_title_bn` VARCHAR(220) DEFAULT NULL AFTER `description_en`,
    ADD COLUMN `seo_title_en` VARCHAR(220) DEFAULT NULL AFTER `seo_title_bn`,
    ADD COLUMN `seo_description_bn` VARCHAR(320) DEFAULT NULL AFTER `seo_title_en`,
    ADD COLUMN `seo_description_en` VARCHAR(320) DEFAULT NULL AFTER `seo_description_bn`;

ALTER TABLE `items`
    ADD COLUMN `seo_title_bn` VARCHAR(220) DEFAULT NULL AFTER `description_en`,
    ADD COLUMN `seo_title_en` VARCHAR(220) DEFAULT NULL AFTER `seo_title_bn`,
    ADD COLUMN `seo_description_bn` VARCHAR(320) DEFAULT NULL AFTER `seo_title_en`,
    ADD COLUMN `seo_description_en` VARCHAR(320) DEFAULT NULL AFTER `seo_description_bn`;

CREATE TABLE IF NOT EXISTS `slug_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type` ENUM('folder', 'item') NOT NULL,
    `entity_id` INT UNSIGNED NOT NULL,
    `old_path` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_sh_type_path` (`entity_type`, `old_path`),
    KEY `idx_sh_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
