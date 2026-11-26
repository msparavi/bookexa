<?php
declare(strict_types=1);

return [
    'description' => 'Technical SEO foundation: guides, authors, redirects, 404 logs, product/item SEO columns, extended slug_history & SEO templates',
    'up' => static function (PDO $pdo): void {
        // 1. Extend items with publisher & isbn (for Schema.org Book structured data)
        mig_add_column_if_missing($pdo, 'items', 'isbn', 'VARCHAR(40) DEFAULT NULL AFTER `author_en`');
        mig_add_column_if_missing($pdo, 'items', 'publisher', 'VARCHAR(160) DEFAULT NULL AFTER `isbn`');

        // 2. Extend products with per-item SEO override fields
        mig_add_column_if_missing($pdo, 'products', 'seo_title_bn', 'VARCHAR(220) DEFAULT NULL AFTER `description_en`');
        mig_add_column_if_missing($pdo, 'products', 'seo_title_en', 'VARCHAR(220) DEFAULT NULL AFTER `seo_title_bn`');
        mig_add_column_if_missing($pdo, 'products', 'seo_description_bn', 'VARCHAR(320) DEFAULT NULL AFTER `seo_title_en`');
        mig_add_column_if_missing($pdo, 'products', 'seo_description_en', 'VARCHAR(320) DEFAULT NULL AFTER `seo_description_bn`');

        // 3. Expand slug_history entity_type enum
        mig_modify_column_if_exists(
            $pdo,
            'slug_history',
            'entity_type',
            "ENUM('folder', 'item', 'product', 'guide', 'author') NOT NULL"
        );

        // 4. Guides table (study guides & preparation articles)
        mig_create_table_if_missing($pdo, 'guides', "
            CREATE TABLE `guides` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `slug` VARCHAR(180) NOT NULL,
                `title_bn` VARCHAR(220) NOT NULL,
                `title_en` VARCHAR(220) NOT NULL,
                `summary_bn` VARCHAR(400) DEFAULT NULL,
                `summary_en` VARCHAR(400) DEFAULT NULL,
                `body_bn` MEDIUMTEXT DEFAULT NULL,
                `body_en` MEDIUMTEXT DEFAULT NULL,
                `seo_title_bn` VARCHAR(220) DEFAULT NULL,
                `seo_title_en` VARCHAR(220) DEFAULT NULL,
                `seo_description_bn` VARCHAR(320) DEFAULT NULL,
                `seo_description_en` VARCHAR(320) DEFAULT NULL,
                `cover` VARCHAR(255) DEFAULT NULL,
                `author_name` VARCHAR(150) DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_guides_slug` (`slug`),
                KEY `idx_guides_active` (`is_active`, `id`),
                FULLTEXT KEY `ft_guides_search` (`title_bn`, `title_en`, `summary_bn`, `summary_en`, `body_bn`, `body_en`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 5. Authors table (dedicated crawlable author profiles)
        mig_create_table_if_missing($pdo, 'authors', "
            CREATE TABLE `authors` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `slug` VARCHAR(160) NOT NULL,
                `name_bn` VARCHAR(160) NOT NULL,
                `name_en` VARCHAR(160) NOT NULL,
                `bio_bn` TEXT DEFAULT NULL,
                `bio_en` TEXT DEFAULT NULL,
                `photo` VARCHAR(255) DEFAULT NULL,
                `seo_title_bn` VARCHAR(220) DEFAULT NULL,
                `seo_title_en` VARCHAR(220) DEFAULT NULL,
                `seo_description_bn` VARCHAR(320) DEFAULT NULL,
                `seo_description_en` VARCHAR(320) DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_authors_slug` (`slug`),
                KEY `idx_authors_active` (`is_active`, `id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 6. Custom Redirect Manager rules (301, 302, 410 Gone)
        mig_create_table_if_missing($pdo, 'seo_redirects', "
            CREATE TABLE `seo_redirects` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `from_path` VARCHAR(255) NOT NULL,
                `to_path` VARCHAR(255) DEFAULT NULL,
                `status_code` SMALLINT UNSIGNED NOT NULL DEFAULT 301,
                `note` VARCHAR(255) DEFAULT NULL,
                `hits` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_seo_redir_from` (`from_path`),
                KEY `idx_seo_redir_code` (`status_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 7. 404 Miss Log (for diagnosing & redirecting broken URLs)
        mig_create_table_if_missing($pdo, 'seo_404_logs', "
            CREATE TABLE `seo_404_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `path` VARCHAR(255) NOT NULL,
                `referrer` VARCHAR(255) DEFAULT NULL,
                `hits` INT UNSIGNED NOT NULL DEFAULT 1,
                `last_seen_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_seo_404_path` (`path`),
                KEY `idx_seo_404_hits` (`hits`, `last_seen_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 8. Default Technical SEO Settings & Meta Templates
        $seoDefaults = [
            'seo_google_verify'               => '',
            'seo_bing_verify'                 => '',
            'seo_default_og_image'            => 'assets/img/apple-touch-icon.png',
            'seo_default_region'              => 'BD',
            'seo_tpl_home_title_bn'           => '{site} — নিরাপদ ডিজিটাল বই ও একাডেমিক পিডিএফ',
            'seo_tpl_home_title_en'           => '{site} — Digital Books & Academic PDF Library',
            'seo_tpl_home_desc_bn'            => 'বিসিএস, ভর্তি, এসএসসি ও এইচএসসি পিডিএফ বই ও স্টাডি গাইড নিরাপদে পড়ুন {site}-এ।',
            'seo_tpl_home_desc_en'            => 'Read protected academic PDFs, BCS prep books, and order printed books online across Bangladesh on {site}.',
            'seo_tpl_folder_title_bn'         => '{title} — পিডিএফ বই ও নোট | {site}',
            'seo_tpl_folder_title_en'         => '{title} — Academic PDFs & Notes | {site}',
            'seo_tpl_folder_desc_bn'          => '{title} ক্যাটাগরির সেরা একাডেমিক পিডিএফ বই ও স্টাডি নোট নিরাপদে পড়ুন ও সংগ্রহ করুন {site}-এ।',
            'seo_tpl_folder_desc_en'          => 'Explore {title} study PDFs, notes, and guides. Preview sample pages securely online on {site}.',
            'seo_tpl_book_title_bn'           => '{title} — {author} | {site}',
            'seo_tpl_book_title_en'           => '{title} by {author} | {site}',
            'seo_tpl_book_desc_bn'            => '{title} ({author}) — {folder}। মূল্য {price}। ফ্রি স্যাম্পল পেজ পড়ুন ও আনলক করুন {site}-এ।',
            'seo_tpl_book_desc_en'            => 'Read {title} by {author} in {folder} ({price}). Preview sample pages securely online on {site}.',
            'seo_tpl_shop_product_title_bn'   => '{title} — {price} | {site}',
            'seo_tpl_shop_product_title_en'   => '{title} ({price}) | {site}',
            'seo_tpl_shop_product_desc_bn'    => '{title} ({author}) প্রিন্টেড বই অর্ডার করুন {price}-এ। সারা বাংলাদেশে দ্রুত হোম ডেলিভারি {site}-এ।',
            'seo_tpl_shop_product_desc_en'    => 'Order printed copy of {title} by {author} for {price} with fast home delivery across Bangladesh on {site}.',
            'seo_tpl_guide_title_bn'          => '{title} — স্টাডি গাইড | {site}',
            'seo_tpl_guide_title_en'          => '{title} — Study Guide | {site}',
            'seo_tpl_guide_desc_bn'           => '{title} সম্পর্কে বিস্তারিত প্রস্তুতি কৌশল, স্টাডি গাইড ও প্রয়োজনীয় বইয়ের তালিকা পড়ুন {site}-এ।',
            'seo_tpl_guide_desc_en'           => 'Read {title}: detailed preparation guide, curated reading tips, and study resources on {site}.',
            'seo_tpl_author_title_bn'         => '{author} — সকল বই ও প্রকাশনা | {site}',
            'seo_tpl_author_title_en'         => '{author} — Books & Publications | {site}',
            'seo_tpl_author_desc_bn'          => '{author}-এর সকল একাডেমিক পিডিএফ ও প্রিন্টেড বইয়ের সম্পূর্ণ তালিকা দেখুন ও পড়ুন {site}-এ।',
            'seo_tpl_author_desc_en'          => 'Browse all digital PDFs and printed books written by {author} available on {site}.',
            'seo_tpl_search_title_bn'         => 'খুঁজুন: {title} | {site}',
            'seo_tpl_search_title_en'         => 'Search: {title} | {site}',
            'seo_tpl_search_desc_bn'          => '{site}-এ একাডেমিক পিডিএফ বই, লেখক ও স্টাডি গাইড মুহূর্তেই খুঁজে নিন এবং অনলাইনে নিরাপদে পড়া শুরু করুন।',
            'seo_tpl_search_desc_en'          => 'Search academic PDFs, books, authors, and study notes quickly on {site} across Bangladesh.',
            'seo_tpl_static_title_bn'         => '{title} | {site}',
            'seo_tpl_static_title_en'         => '{title} | {site}',
            'seo_tpl_static_desc_bn'          => '{title} সম্পর্কিত বিস্তারিত তথ্য, নীতিমালা এবং পাঠক সহায়তা নির্দেশিকা দেখুন {site} প্ল্যাটফর্মে।',
            'seo_tpl_static_desc_en'          => 'Read important information, platform policies, and reader support details for {title} on {site}.',
        ];
        foreach ($seoDefaults as $k => $v) {
            mig_insert_setting_if_missing($pdo, $k, $v);
        }
    },
];
