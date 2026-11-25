-- ============================================================================
-- Bookexa — Complete Database Schema & Seed Data
-- Charset: utf8mb4 | Collation: utf8mb4_unicode_ci (Full Bangla + English)
-- Import via phpMyAdmin into the `bookexa` database.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `active_session_token` VARCHAR(128) DEFAULT NULL,
    `active_session_updated_at` DATETIME DEFAULT NULL,
    `reader_theme` ENUM('dark', 'sepia', 'light') DEFAULT NULL,
    `reader_zoom` DECIMAL(4,2) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_users_email` (`email`),
    KEY `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('superadmin', 'editor') NOT NULL DEFAULT 'superadmin',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at` DATETIME DEFAULT NULL,
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `banners` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title_bn` VARCHAR(200) NOT NULL,
    `title_en` VARCHAR(200) NOT NULL,
    `subtitle_bn` VARCHAR(300) DEFAULT NULL,
    `subtitle_en` VARCHAR(300) DEFAULT NULL,
    `cta_bn` VARCHAR(80) DEFAULT 'এখনই দেখুন',
    `cta_en` VARCHAR(80) DEFAULT 'Explore Now',
    `link_url` VARCHAR(255) NOT NULL DEFAULT '/pdf',
    `image` VARCHAR(255) NOT NULL,
    `mobile_image` VARCHAR(255) DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `starts_at` DATETIME DEFAULT NULL,
    `ends_at` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_banners_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `folders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `slug` VARCHAR(140) NOT NULL,
    `name_bn` VARCHAR(180) NOT NULL,
    `name_en` VARCHAR(180) NOT NULL,
    `description_bn` TEXT DEFAULT NULL,
    `description_en` TEXT DEFAULT NULL,
    `thumbnail` VARCHAR(255) DEFAULT NULL,
    `accent_color` VARCHAR(20) DEFAULT '#0F3D3E',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_folders_parent_id` (`parent_id`),
    KEY `idx_folders_slug` (`slug`),
    KEY `idx_folders_active_sort` (`is_active`, `sort_order`),
    CONSTRAINT `fk_folders_parent` FOREIGN KEY (`parent_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `folder_id` INT UNSIGNED DEFAULT NULL,
    `slug` VARCHAR(160) NOT NULL,
    `title_bn` VARCHAR(220) NOT NULL,
    `title_en` VARCHAR(220) NOT NULL,
    `author_bn` VARCHAR(150) DEFAULT NULL,
    `author_en` VARCHAR(150) DEFAULT NULL,
    `description_bn` TEXT DEFAULT NULL,
    `description_en` TEXT DEFAULT NULL,
    `cover` VARCHAR(255) DEFAULT NULL,
    `drive_link` TEXT NOT NULL,
    `sample_drive_link` TEXT DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `original_price` DECIMAL(10,2) DEFAULT NULL,
    `is_free` TINYINT(1) NOT NULL DEFAULT 0,
    `sample_pages` INT UNSIGNED NOT NULL DEFAULT 4,
    `total_pages` INT UNSIGNED DEFAULT NULL,
    `pages_status` ENUM('unprocessed', 'processing', 'ready') NOT NULL DEFAULT 'unprocessed',
    `processed_at` DATETIME DEFAULT NULL,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_items_slug` (`slug`),
    KEY `idx_items_slug` (`slug`),
    KEY `idx_items_folder_id` (`folder_id`),
    KEY `idx_items_active` (`is_active`, `is_featured`),
    CONSTRAINT `fk_items_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_code` VARCHAR(40) NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `original_amount` DECIMAL(10,2) DEFAULT NULL,
    `coupon_id` INT UNSIGNED DEFAULT NULL,
    `coupon_code` VARCHAR(40) DEFAULT NULL,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_method` ENUM('bkash', 'nagad', 'rocket', 'free') NOT NULL DEFAULT 'bkash',
    `sender_number` VARCHAR(40) DEFAULT NULL,
    `transaction_id` VARCHAR(80) DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `admin_note` VARCHAR(255) DEFAULT NULL,
    `reviewed_by` INT UNSIGNED DEFAULT NULL,
    `reviewed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_orders_code` (`order_code`),
    KEY `idx_orders_user` (`user_id`),
    KEY `idx_orders_status` (`status`),
    KEY `idx_orders_coupon` (`coupon_id`),
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_orders_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `library` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `order_id` INT UNSIGNED DEFAULT NULL,
    `granted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_library_user_item` (`user_id`, `item_id`),
    CONSTRAINT `fk_lib_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lib_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reader_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `token_hash` CHAR(64) NOT NULL,
    `jti` CHAR(32) NOT NULL DEFAULT '',
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `is_sample` TINYINT(1) NOT NULL DEFAULT 0,
    `session_id` VARCHAR(128) NOT NULL DEFAULT '',
    `session_token` VARCHAR(128) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_reader_token_hash` (`token_hash`),
    KEY `idx_rt_user_expires` (`user_id`, `expires_at`),
    CONSTRAINT `fk_rt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rt_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reading_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED DEFAULT NULL,
    `is_sample` TINYINT(1) NOT NULL DEFAULT 0,
    `action` VARCHAR(50) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `details` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rlogs_user_time` (`user_id`, `created_at`),
    KEY `idx_rlogs_item_time` (`item_id`, `created_at`),
    CONSTRAINT `fk_rlogs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rlogs_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(80) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `language_strings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lang_key` VARCHAR(100) NOT NULL,
    `text_bn` TEXT NOT NULL,
    `text_en` TEXT NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_lang_key` (`lang_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `rate_key` VARCHAR(120) NOT NULL,
    `hits` INT UNSIGNED NOT NULL DEFAULT 1,
    `window_start` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`rate_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_pr_token_hash` (`token_hash`),
    KEY `idx_pr_user` (`user_id`),
    CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reading_progress` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `last_page` INT UNSIGNED NOT NULL DEFAULT 1,
    `total_pages` INT UNSIGNED NOT NULL DEFAULT 1,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_rp_user_item` (`user_id`, `item_id`),
    CONSTRAINT `fk_rp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id` INT UNSIGNED DEFAULT NULL,
    `admin_name` VARCHAR(120) NOT NULL,
    `action` VARCHAR(80) NOT NULL,
    `target_type` VARCHAR(50) DEFAULT NULL,
    `target_id` INT UNSIGNED DEFAULT NULL,
    `details` VARCHAR(255) DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_alogs_admin` (`admin_id`),
    KEY `idx_alogs_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `security_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_type` VARCHAR(60) NOT NULL,
    `identifier` VARCHAR(190) DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `details` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_slogs_type` (`event_type`),
    KEY `idx_slogs_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wishlist` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_wishlist_user_item` (`user_id`, `item_id`),
    KEY `idx_wishlist_item` (`item_id`),
    CONSTRAINT `fk_wish_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wish_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reviews` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `rating` TINYINT UNSIGNED NOT NULL,
    `comment` TEXT NOT NULL,
    `status` ENUM('approved', 'pending', 'hidden') NOT NULL DEFAULT 'approved',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_review_user_item` (`user_id`, `item_id`),
    KEY `idx_reviews_item_status` (`item_id`, `status`),
    CONSTRAINT `fk_rev_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rev_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coupons` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(40) NOT NULL,
    `discount_type` ENUM('percent', 'fixed') NOT NULL DEFAULT 'percent',
    `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `min_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `usage_limit` INT UNSIGNED DEFAULT NULL,
    `used_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `per_user_limit` INT UNSIGNED NOT NULL DEFAULT 1,
    `applies_to_all` TINYINT(1) NOT NULL DEFAULT 1,
    `expires_at` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_coupon_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coupon_items` (
    `coupon_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`coupon_id`, `item_id`),
    CONSTRAINT `fk_ci_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ci_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `folder_visits` (
    `user_id` INT UNSIGNED NOT NULL,
    `folder_id` INT UNSIGNED NOT NULL,
    `last_visited_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `folder_id`),
    CONSTRAINT `fk_fv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fv_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `type` VARCHAR(40) NOT NULL,
    `title_bn` VARCHAR(180) NOT NULL,
    `title_en` VARCHAR(180) NOT NULL,
    `body_bn` VARCHAR(255) DEFAULT NULL,
    `body_en` VARCHAR(255) DEFAULT NULL,
    `link_url` VARCHAR(255) DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notif_user_read` (`user_id`, `is_read`, `id`),
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `media_sync` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rel_path` VARCHAR(255) NOT NULL,
    `local_exists` TINYINT(1) NOT NULL DEFAULT 1,
    `cdn_synced` TINYINT(1) NOT NULL DEFAULT 0,
    `cdn_synced_at` DATETIME DEFAULT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_error` VARCHAR(255) DEFAULT NULL,
    `size` INT UNSIGNED NOT NULL DEFAULT 0,
    `sha1` CHAR(40) DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_media_rel_path` (`rel_path`),
    KEY `idx_media_sync_status` (`cdn_synced`, `local_exists`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DEFAULT SYSTEM SETTINGS & CORE LANGUAGE STRINGS (Production Safe)
-- Zero demo accounts or sample passwords are included here.
-- Use /install on first deployment to create your Super Admin account.
-- ============================================================================
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'Bookexa'),
('site_logo', 'assets/img/logo.svg'),
('site_favicon', 'assets/img/favicon.svg'),
('default_language', 'bn'),
('maintenance_mode', '0'),
('shop_enabled', '0'),
('free_pdfjs_fallback', '1'),
('contact_phone', '+880 1700-000001'),
('contact_email', 'support@bookexa.com'),
('contact_address', 'Dhaka, Bangladesh'),
('bkash_number', '01711-000111 (Personal - Send Money)'),
('nagad_number', '01811-000222 (Personal - Send Money)'),
('rocket_number', '01911-000333-4 (Personal)'),
('payment_instructions_bn', 'উপরের যেকোনো নম্বরে Send Money করে নিচে আপনার প্রেরক নম্বর ও Transaction ID (TrxID) জমা দিন। অ্যাডমিন যাচাই করার সাথে সাথেই বইটি আনলক হবে।'),
('payment_instructions_en', 'Send Money to any number above, then submit your sender number and Transaction ID (TrxID). Once Admin approves, the PDF unlocks in My Library.'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_from_name', 'Bookexa'),
('smtp_from_email', 'no-reply@bookexa.com'),
('smtp_encryption', 'tls'),
('legal_terms_bn', '<h2>ব্যবহারের শর্তাবলি</h2><p>Bookexa-তে নিবন্ধন ও ডিজিটাল বই ব্যবহারের মাধ্যমে আপনি এই শর্তাবলিতে সম্মত হচ্ছেন। আপনার অ্যাকাউন্ট ব্যক্তিগত ব্যবহারের জন্য—লগইন তথ্য বা ক্রয়কৃত কনটেন্ট অন্যের সাথে শেয়ার করা নিষিদ্ধ।</p><p>প্ল্যাটফর্মের সকল পিডিএফ শুধুমাত্র সুরক্ষিত ইন-ব্রাউজার রিডারে পড়ার জন্য সরবরাহ করা হয়।</p>'),
('legal_terms_en', '<h2>Terms of Use</h2><p>By registering and reading digital books on Bookexa, you agree to use your account strictly for personal study. Sharing account credentials or redistributing purchased content is prohibited.</p><p>All PDFs are licensed for viewing inside Bookexa\'s protected in-browser reader only.</p>'),
('legal_privacy_bn', '<h2>গোপনীয়তা নীতি</h2><p>আমরা কেবল অ্যাকাউন্ট তৈরি, অর্ডার যাচাই, রিডিং সেশন সুরক্ষা ও প্রয়োজনীয় নোটিফিকেশনের জন্য আপনার নাম, ইমেইল, ফোন নম্বর ও রিডিং অগ্রগতি সংরক্ষণ করি।</p><p>ব্যবহারকারীর ব্যক্তিগত তথ্য তৃতীয় পক্ষের কাছে বিক্রি বা হস্তান্তর করা হয় না।</p>'),
('legal_privacy_en', '<h2>Privacy Policy</h2><p>We collect your name, email, phone number, and reading progress solely to provide library access, verify manual payments, secure single-session reading, and send essential account notifications.</p><p>We never sell personal data to third parties.</p>'),
('legal_refund_bn', '<h2>রিফান্ড নীতি</h2><p>ডিজিটাল পিডিএফ আনলক হয়ে যাওয়ার পর সাধারণত অর্থ ফেরতযোগ্য নয়। তবে ভুলবশত ডাবল পেমেন্ট হলে বা অর্ডার বাতিল হলে ৪৮ ঘণ্টার মধ্যে সাপোর্টে যোগাযোগ করলে যাচাই সাপেক্ষে রিফান্ড করা হবে।</p>'),
('legal_refund_en', '<h2>Refund Policy</h2><p>Digital PDFs are non-refundable once unlocked in My Library. For duplicate transactions or rejected payments, contact support within 48 hours for verification and refund.</p>'),
('legal_copyright_bn', '<h2>কপিরাইট ও ওয়াটারমার্ক বিজ্ঞপ্তি</h2><p>Bookexa রিডারের প্রতিটি পৃষ্ঠায় পাঠকের নাম, ফোন নম্বর/ইমেইল, ইউজার আইডি ও সময় সংবলিত ডায়নামিক ওয়াটারমার্ক যুক্ত থাকে। কোনো বই বা পৃষ্ঠার স্ক্রিনশট, কপি, প্রিন্ট বা অননুমোদিত শেয়ার করা কপিরাইট আইনে দণ্ডনীয় এবং অ্যাকাউন্ট স্থায়ীভাবে বন্ধ হতে পারে।</p>'),
('legal_copyright_en', '<h2>Copyright &amp; Watermark Notice</h2><p>Every page rendered in Bookexa Reader carries a dynamic forensic watermark containing your name, email/phone, user ID, and timestamp. Reproducing, recording, distributing, or sharing any page is strictly prohibited and may result in account termination and legal action.</p>')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

INSERT INTO `language_strings` (`lang_key`, `text_bn`, `text_en`) VALUES
('site_name', 'Bookexa', 'Bookexa'),
('tagline', 'নিরাপদ ডিজিটাল বই ও একাডেমিক পিডিএফ ভুবন', 'Secure Digital Books & Study PDF Platform'),
('nav_home', 'হোম', 'Home'),
('nav_pdfs', 'পিডিএফ সংগ্রহ', 'Browse PDFs'),
('nav_library', 'আমার লাইব্রেরি', 'My Library'),
('nav_login', 'লগইন', 'Login')
ON DUPLICATE KEY UPDATE `text_bn` = VALUES(`text_bn`);

SET FOREIGN_KEY_CHECKS = 1;
