<?php
declare(strict_types=1);

return [
    'description' => 'Server load optimization (composite indexes, stored folder counts, lightweight mobile & concurrency settings)',
    'up' => static function (PDO $pdo): void {
        mig_add_index_if_missing($pdo, 'orders', 'idx_orders_user_status', 'INDEX `idx_orders_user_status` (`user_id`, `status`)');
        mig_add_index_if_missing($pdo, 'reading_logs', 'idx_rlogs_user_time', 'INDEX `idx_rlogs_user_time` (`user_id`, `created_at`)');
        mig_add_index_if_missing($pdo, 'notifications', 'idx_notif_user_read', 'INDEX `idx_notif_user_read` (`user_id`, `is_read`, `id`)');
        mig_add_index_if_missing($pdo, 'items', 'idx_items_folder_active', 'INDEX `idx_items_folder_active` (`folder_id`, `is_active`)');

        mig_add_column_if_missing($pdo, 'folders', 'subfolder_count', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `sort_order`');
        mig_add_column_if_missing($pdo, 'folders', 'item_count', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `subfolder_count`');
        mig_add_index_if_missing($pdo, 'folders', 'idx_folders_parent_active', 'INDEX `idx_folders_parent_active` (`parent_id`, `is_active`, `sort_order`)');

        if (mig_column_exists($pdo, 'folders', 'subfolder_count') && mig_column_exists($pdo, 'folders', 'item_count')) {
            $pubCondF = (mig_column_exists($pdo, 'folders', 'publish_at') && mig_column_exists($pdo, 'folders', 'unpublish_at'))
                ? 'AND (publish_at IS NULL OR publish_at <= NOW()) AND (unpublish_at IS NULL OR unpublish_at > NOW())'
                : '';
            $pubCondI = (mig_column_exists($pdo, 'items', 'publish_at') && mig_column_exists($pdo, 'items', 'unpublish_at'))
                ? 'AND (publish_at IS NULL OR publish_at <= NOW()) AND (unpublish_at IS NULL OR unpublish_at > NOW())'
                : '';

            $pdo->exec(
                "UPDATE `folders` f
                 LEFT JOIN (
                     SELECT parent_id AS fid, COUNT(*) AS cnt
                     FROM folders
                     WHERE is_active = 1 AND parent_id IS NOT NULL {$pubCondF}
                     GROUP BY parent_id
                 ) sc ON sc.fid = f.id
                 LEFT JOIN (
                     SELECT folder_id AS fid, COUNT(*) AS cnt
                     FROM items
                     WHERE is_active = 1 AND folder_id IS NOT NULL {$pubCondI}
                     GROUP BY folder_id
                 ) ic ON ic.fid = f.id
                 SET f.subfolder_count = COALESCE(sc.cnt, 0),
                     f.item_count = COALESCE(ic.cnt, 0)"
            );
        }

        mig_insert_setting_if_missing($pdo, 'reader_mobile_lightweight', '1');
        mig_insert_setting_if_missing($pdo, 'max_concurrent_page_renders', '8');
    },
];
