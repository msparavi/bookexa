<?php
declare(strict_types=1);

return [
    'description' => 'Physical book Shopping module (categories, products, gallery, stock logs, reviews, wishlist, carts, shop orders & delivery settings)',
    'up' => static function (PDO $pdo): void {
        migration_ensure_tracking_table($pdo);

        mig_create_table_if_missing($pdo, 'shop_categories', "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        mig_create_table_if_missing($pdo, 'products', "
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
                KEY `idx_prod_stock` (`is_active`, `stock`),
                FULLTEXT KEY `ft_products_search` (`title_bn`, `title_en`, `author_bn`, `author_en`, `publisher`, `isbn`, `description_bn`, `description_en`),
                CONSTRAINT `fk_shop_prod_cat` FOREIGN KEY (`category_id`) REFERENCES `shop_categories` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        mig_create_table_if_missing($pdo, 'product_images', "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        mig_create_table_if_missing($pdo, 'product_stock_logs', "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        mig_create_table_if_missing($pdo, 'product_wishlist', "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        mig_create_table_if_missing($pdo, 'product_reviews', "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        mig_create_table_if_missing($pdo, 'shop_carts', "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        mig_create_table_if_missing($pdo, 'shop_cart_items', "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        mig_create_table_if_missing($pdo, 'shop_orders', "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        mig_create_table_if_missing($pdo, 'shop_order_items', "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        mig_insert_setting_if_missing($pdo, 'shop_cod_enabled', '1');
        mig_insert_setting_if_missing($pdo, 'shop_delivery_dhaka', '60');
        mig_insert_setting_if_missing($pdo, 'shop_delivery_outside', '120');
        mig_insert_setting_if_missing($pdo, 'shop_delivery_district_json', '{}');
        mig_insert_setting_if_missing($pdo, 'shop_free_delivery_above', '1500');
        mig_insert_setting_if_missing($pdo, 'shop_est_days_dhaka', '1-2 days');
        mig_insert_setting_if_missing($pdo, 'shop_est_days_outside', '3-5 days');
        mig_insert_setting_if_missing($pdo, 'shop_return_days', '7');
    },
];
