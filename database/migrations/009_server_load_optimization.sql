-- Bookexa Migration 009: Server Load Optimization (Composite Indexes, Stored Folder Counts, Reader Mobile Lightweight & Concurrency Settings)

ALTER TABLE `orders`
    ADD INDEX `idx_orders_user_status` (`user_id`, `status`);

ALTER TABLE `reading_logs`
    ADD INDEX `idx_rlogs_user_time` (`user_id`, `created_at`);

ALTER TABLE `notifications`
    ADD INDEX `idx_notif_user_read` (`user_id`, `is_read`, `id`);

ALTER TABLE `items`
    ADD INDEX `idx_items_folder_active` (`folder_id`, `is_active`);

ALTER TABLE `folders`
    ADD COLUMN `subfolder_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `sort_order`,
    ADD COLUMN `item_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `subfolder_count`,
    ADD INDEX `idx_folders_parent_active` (`parent_id`, `is_active`, `sort_order`);

UPDATE `folders` f
LEFT JOIN (
    SELECT parent_id AS fid, COUNT(*) AS cnt
    FROM folders
    WHERE is_active = 1 AND parent_id IS NOT NULL
      AND (publish_at IS NULL OR publish_at <= NOW())
      AND (unpublish_at IS NULL OR unpublish_at > NOW())
    GROUP BY parent_id
) sc ON sc.fid = f.id
LEFT JOIN (
    SELECT folder_id AS fid, COUNT(*) AS cnt
    FROM items
    WHERE is_active = 1 AND folder_id IS NOT NULL
      AND (publish_at IS NULL OR publish_at <= NOW())
      AND (unpublish_at IS NULL OR unpublish_at > NOW())
    GROUP BY folder_id
) ic ON ic.fid = f.id
SET f.subfolder_count = COALESCE(sc.cnt, 0),
    f.item_count = COALESCE(ic.cnt, 0);

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('reader_mobile_lightweight', '1'),
('max_concurrent_page_renders', '8')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;
