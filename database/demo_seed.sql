-- ============================================================================
-- Bookexa Local Development Demo Seed Data (DO NOT import in production!)
-- ============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO `admins` (`id`, `name`, `email`, `password_hash`, `role`, `is_active`, `must_change_password`) VALUES
(1, 'Bookexa Admin', 'admin@bookexa.com', '$2y$10$I1kxVBpsW7JKaJcBzPxGNuVww9RDMugqwhqFUiYFoAZYUlXvwLcwa', 'superadmin', 1, 1)
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `is_active`) VALUES
(1, 'Tariqul Islam', 'reader@bookexa.com', '01811223344', '$2y$10$GHty40Y92suKy4dB9i1sCOGvNCHeQGWhZu9a9QXCFXHulcq4bcX56', 1),
(2, 'Nusrat Jahan', 'nusrat@bookexa.com', '01755443322', '$2y$10$GHty40Y92suKy4dB9i1sCOGvNCHeQGWhZu9a9QXCFXHulcq4bcX56', 1)
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

INSERT INTO `banners` (`id`, `title_bn`, `title_en`, `subtitle_bn`, `subtitle_en`, `cta_bn`, `cta_en`, `link_url`, `image`, `sort_order`, `is_active`) VALUES
(1, 'নতুন শিক্ষাবর্ষের সেরা পিডিএফ সংগ্রহ', 'Best Digital Books for the New Academic Year', 'একাডেমিক, চাকরি ও ভর্তি পরীক্ষার প্রিমিয়াম নোট পড়ুন নিরাপদ রিডারে।', 'Read curated Academic, Job & Admission PDFs inside our watermarked reader.', 'সংগ্রহ দেখুন', 'Browse PDFs', '/pdf', 'uploads/banners/banner-1.svg', 1, 1),
(2, 'আর্টস ও ড্রয়িং বিশেষ হ্যান্ডবুক', 'Arts, Culture & Drawing Handbooks', 'সৃজনশীল চর্চা ও স্কেচিং শেখার ডিজিটাল গাইড।', 'Step-by-step digital guides for sketching, culture, and visual arts.', 'দেখুন', 'Explore', '/pdf/drawing', 'uploads/banners/banner-2.svg', 2, 1)
ON DUPLICATE KEY UPDATE `title_bn` = VALUES(`title_bn`);

INSERT INTO `folders` (`id`, `parent_id`, `slug`, `name_bn`, `name_en`, `description_bn`, `description_en`, `thumbnail`, `accent_color`, `sort_order`, `is_active`) VALUES
(1, NULL, 'academic', 'একাডেমিক', 'Academic', 'স্কুল, কলেজ ও বোর্ড পরীক্ষার নোট ও পাঠ্যবই।', 'School, college, and board exam study guides & notes.', 'uploads/folders/academic.svg', '#232766', 1, 1),
(2, NULL, 'job', 'চাকরি ও বিসিএস', 'Job', 'বিসিএস, ব্যাংক ও সরকারি চাকরি প্রস্তুতির সম্পূর্ণ গাইড।', 'BCS, bank, and government job preparation guides.', 'uploads/folders/job.svg', '#8B3A2B', 2, 1),
(3, NULL, 'admission', 'ভর্তি প্রস্তুতি', 'Admission', 'বিশ্ববিদ্যালয়, মেডিকেল ও ইঞ্জিনিয়ারিং ভর্তি প্রশ্নব্যাংক।', 'University, medical, and engineering admission question banks.', 'uploads/folders/admission.svg', '#1B4D89', 3, 1),
(4, NULL, 'religious-knowledge', 'ধর্মীয় জ্ঞান', 'Religious Knowledge', 'নৈতিক শিক্ষা, ইতিহাস ও ধর্মীয় প্রামাণ্য বই।', 'Moral studies, history, and authentic religious books.', 'uploads/folders/religious.svg', '#14532D', 4, 1),
(5, NULL, 'arts-and-culture', 'শিল্প ও সংস্কৃতি', 'Arts and Culture', 'বাংলা সাহিত্য, ঐতিহ্য ও সংস্কৃতি বিষয়ক সংকলন।', 'Literature, heritage, and arts & culture collections.', 'uploads/folders/arts.svg', '#5B2C6F', 5, 1),
(6, NULL, 'drawing', 'অঙ্কন ও চিত্রকলা', 'Drawing', 'স্কেচিং, রঙ ও চিত্রাঙ্কন শেখার ধাপে ধাপে পিডিএফ।', 'Step-by-step sketching, coloring, and drawing PDFs.', 'uploads/folders/drawing.svg', '#9A3412', 6, 1),
(7, 1, 'ssc-class-9-10', 'এসএসসি (৯ম-১০ম শ্রেণি)', 'SSC (Class 9-10)', 'নবম-দশম শ্রেণির অধ্যায়ভিত্তিক হ্যান্ডনোট।', 'Chapter-wise handnotes for SSC students.', 'uploads/folders/ssc.svg', '#1E4E5A', 1, 1),
(8, 7, 'bangla', 'বাংলা ১ম ও ২য় পত্র', 'Bangla 1st & 2nd Paper', 'ব্যাকরণ, রচনা ও সৃজনশীল প্রশ্নোত্তর।', 'Grammar, essay, and creative Q&A.', 'uploads/folders/bangla.svg', '#9E3B25', 1, 1)
ON DUPLICATE KEY UPDATE `name_bn` = VALUES(`name_bn`);

INSERT INTO `items` (`id`, `folder_id`, `slug`, `title_bn`, `title_en`, `author_bn`, `author_en`, `description_bn`, `description_en`, `cover`, `drive_link`, `price`, `original_price`, `is_free`, `sample_pages`, `total_pages`, `is_featured`, `is_active`, `sort_order`) VALUES
(1, 8, 'ssc-bangla-masternote', 'এসএসসি বাংলা ব্যাকরণ ও রচনা মাস্টারনোট', 'SSC Bangla Grammar & Essay Master Note', 'অধ্যাপক রাশেদুল করিম', 'Prof. Rashedul Karim', 'বাংলা ২য় পত্রের পূর্ণাঙ্গ সহজ নোট।', 'Complete easy notes for Bangla 2nd paper grammar and essays.', 'uploads/covers/cover-bangla.svg', 'local://sample-book.pdf', 149.00, 220.00, 0, 4, 120, 1, 1, 1),
(2, 2, 'bcs-preliminary-digest', '৪৭তম বিসিএস প্রিলিমিনারি ডাইজেস্ট', '47th BCS Preliminary Complete Digest', 'Bookexa একাডেমিক টিম', 'Bookexa Academic Team', '২,৫০০+ প্রশ্নের ব্যাখ্যাসহ সমাধান।', '2,500+ curated MCQs with clear explanations.', 'uploads/covers/cover-bcs.svg', 'local://sample-book.pdf', 250.00, 350.00, 0, 5, 240, 1, 1, 2),
(3, 3, 'medical-varsity-biology', 'মেডিকেল ও ভার্সিটি বায়োলজি প্রশ্নব্যাংক', 'Medical & Varsity Biology Question Bank', 'ডা. সাদিয়া আফরিন', 'Dr. Sadia Afrin', 'বিগত ১০ বছরের ভর্তি প্রশ্নের অধ্যায়ভিত্তিক বিশ্লেষণ।', 'Chapter-wise analysis of past 10 years of admission questions.', 'uploads/covers/cover-hsc.svg', 'local://sample-book.pdf', 0.00, NULL, 1, 4, 96, 1, 1, 3),
(4, 6, 'sketching-basics-workbook', 'বেসিক পেন্সিল স্কেচিং ও ড্রয়িং ওয়ার্কবুক', 'Basic Pencil Sketching & Drawing Workbook', 'তানভীরুল ইসলাম', 'Tanvirul Islam', 'শুরু থেকে পোর্ট্রেট ও ল্যান্ডস্কেপ অঙ্কনের সহজ অনুশীলন।', 'Easy step-by-step practice for portrait and landscape sketching.', 'uploads/covers/cover-literature.svg', 'local://sample-book.pdf', 120.00, 180.00, 0, 3, 64, 1, 1, 4)
ON DUPLICATE KEY UPDATE `title_bn` = VALUES(`title_bn`);

INSERT INTO `orders` (`id`, `order_code`, `user_id`, `item_id`, `amount`, `payment_method`, `sender_number`, `transaction_id`, `status`, `reviewed_by`, `reviewed_at`) VALUES
(1, 'BX-20261125-1001', 1, 1, 149.00, 'bkash', '01811223344', 'TRX9K82LMN', 'approved', 1, NOW()),
(2, 'BX-20261125-1002', 2, 2, 250.00, 'nagad', '01755443322', 'NGD7741PQR', 'pending', NULL, NULL)
ON DUPLICATE KEY UPDATE `order_code` = VALUES(`order_code`);

INSERT INTO `library` (`user_id`, `item_id`, `order_id`) VALUES
(1, 1, 1)
ON DUPLICATE KEY UPDATE `order_id` = VALUES(`order_id`);

INSERT INTO `reading_logs` (`user_id`, `item_id`, `is_sample`, `action`, `ip_address`, `user_agent`) VALUES
(1, 1, 0, 'open_reader', '127.0.0.1', 'Mozilla/5.0')
ON DUPLICATE KEY UPDATE `action` = VALUES(`action`);

SET FOREIGN_KEY_CHECKS = 1;
