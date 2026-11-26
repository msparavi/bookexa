<?php
declare(strict_types=1);

return [
    'description' => 'Core Web Vitals & server performance indexes for items, banners, notifications, guides, and shop products',
    'up' => static function (PDO $pdo): void {
        mig_add_index_if_missing($pdo, 'items', 'idx_items_active_sort', 'KEY `idx_items_active_sort` (`is_active`, `sort_order`, `id`)');
        mig_add_index_if_missing($pdo, 'items', 'idx_items_active_free', 'KEY `idx_items_active_free` (`is_active`, `is_free`, `price`)');
        mig_add_index_if_missing($pdo, 'items', 'idx_items_active_pop', 'KEY `idx_items_active_pop` (`is_active`, `read_count`, `view_count`)');
        mig_add_index_if_missing($pdo, 'items', 'idx_items_active_pub', 'KEY `idx_items_active_pub` (`is_active`, `publish_at`, `unpublish_at`)');
        mig_add_index_if_missing($pdo, 'items', 'idx_items_author_en', 'KEY `idx_items_author_en` (`is_active`, `author_en`)');
        mig_add_index_if_missing($pdo, 'items', 'idx_items_author_bn', 'KEY `idx_items_author_bn` (`is_active`, `author_bn`)');

        mig_add_index_if_missing($pdo, 'banners', 'idx_banners_pub', 'KEY `idx_banners_pub` (`is_active`, `publish_at`, `unpublish_at`, `sort_order`)');
        mig_add_index_if_missing($pdo, 'notifications', 'idx_notif_user_unread', 'KEY `idx_notif_user_unread` (`user_id`, `is_read`, `id`)');
        mig_add_index_if_missing($pdo, 'guides', 'idx_guides_active_upd', 'KEY `idx_guides_active_upd` (`is_active`, `updated_at`, `id`)');
        mig_add_index_if_missing($pdo, 'products', 'idx_prod_active_feat', 'KEY `idx_prod_active_feat` (`is_active`, `is_featured`, `id`)');

        mig_insert_setting_if_missing($pdo, 'debug_timing', '0');
    },
];
