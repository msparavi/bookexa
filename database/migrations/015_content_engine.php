<?php
declare(strict_types=1);

return [
    'description' => 'Content engine: guides rich metadata/scheduling/relations, E-E-A-T authors, folder editorial blocks, book structured sections, hubs, editorial FAQs, E-E-A-T policies & starter guides',
    'up' => static function (PDO $pdo): void {
        // 1. Extend guides table
        mig_add_column_if_missing($pdo, 'guides', 'category_slug', 'VARCHAR(120) DEFAULT NULL AFTER `slug`');
        mig_add_column_if_missing($pdo, 'guides', 'category_bn', 'VARCHAR(140) DEFAULT NULL AFTER `category_slug`');
        mig_add_column_if_missing($pdo, 'guides', 'category_en', 'VARCHAR(140) DEFAULT NULL AFTER `category_bn`');
        mig_add_column_if_missing($pdo, 'guides', 'tags', 'VARCHAR(320) DEFAULT NULL AFTER `category_en`');
        mig_add_column_if_missing($pdo, 'guides', 'author_id', 'INT UNSIGNED DEFAULT NULL AFTER `author_name`');
        mig_add_column_if_missing($pdo, 'guides', 'reading_minutes', 'TINYINT UNSIGNED NOT NULL DEFAULT 4 AFTER `author_id`');
        mig_add_column_if_missing($pdo, 'guides', 'related_item_ids', 'VARCHAR(255) DEFAULT NULL AFTER `reading_minutes`');
        mig_add_column_if_missing($pdo, 'guides', 'related_folder_ids', 'VARCHAR(255) DEFAULT NULL AFTER `related_item_ids`');
        mig_add_column_if_missing($pdo, 'guides', 'publish_at', 'DATETIME DEFAULT NULL AFTER `is_active`');
        mig_add_column_if_missing($pdo, 'guides', 'content_hash', 'CHAR(64) DEFAULT NULL AFTER `publish_at`');
        mig_add_index_if_missing($pdo, 'guides', 'idx_guides_cat', 'KEY `idx_guides_cat` (`category_slug`, `is_active`)');
        mig_add_index_if_missing($pdo, 'guides', 'idx_guides_sched', 'KEY `idx_guides_sched` (`is_active`, `publish_at`)');
        mig_add_index_if_missing($pdo, 'guides', 'idx_guides_hash', 'KEY `idx_guides_hash` (`content_hash`)');

        // 2. Extend authors table (E-E-A-T)
        mig_add_column_if_missing($pdo, 'authors', 'expertise_bn', 'VARCHAR(220) DEFAULT NULL AFTER `name_en`');
        mig_add_column_if_missing($pdo, 'authors', 'expertise_en', 'VARCHAR(220) DEFAULT NULL AFTER `expertise_bn`');
        mig_add_column_if_missing($pdo, 'authors', 'social_facebook', 'VARCHAR(255) DEFAULT NULL AFTER `photo`');
        mig_add_column_if_missing($pdo, 'authors', 'social_linkedin', 'VARCHAR(255) DEFAULT NULL AFTER `social_facebook`');
        mig_add_column_if_missing($pdo, 'authors', 'social_website', 'VARCHAR(255) DEFAULT NULL AFTER `social_linkedin`');

        // 3. Extend folders table (Landing editorial intro, who is this for, FAQs)
        mig_add_column_if_missing($pdo, 'folders', 'intro_bn', 'MEDIUMTEXT DEFAULT NULL AFTER `description_en`');
        mig_add_column_if_missing($pdo, 'folders', 'intro_en', 'MEDIUMTEXT DEFAULT NULL AFTER `intro_bn`');
        mig_add_column_if_missing($pdo, 'folders', 'who_for_bn', 'TEXT DEFAULT NULL AFTER `intro_en`');
        mig_add_column_if_missing($pdo, 'folders', 'who_for_en', 'TEXT DEFAULT NULL AFTER `who_for_bn`');
        mig_add_column_if_missing($pdo, 'folders', 'faqs_json', 'MEDIUMTEXT DEFAULT NULL AFTER `who_for_en`');

        // 4. Extend items table (Structured public book content — strictly metadata, never protected text)
        mig_add_column_if_missing($pdo, 'items', 'what_you_learn_bn', 'TEXT DEFAULT NULL AFTER `description_en`');
        mig_add_column_if_missing($pdo, 'items', 'what_you_learn_en', 'TEXT DEFAULT NULL AFTER `what_you_learn_bn`');
        mig_add_column_if_missing($pdo, 'items', 'who_for_bn', 'TEXT DEFAULT NULL AFTER `what_you_learn_en`');
        mig_add_column_if_missing($pdo, 'items', 'who_for_en', 'TEXT DEFAULT NULL AFTER `who_for_bn`');
        mig_add_column_if_missing($pdo, 'items', 'edition', 'VARCHAR(80) DEFAULT NULL AFTER `publisher`');
        mig_add_column_if_missing($pdo, 'items', 'book_language', 'VARCHAR(60) DEFAULT NULL AFTER `edition`');
        mig_add_column_if_missing($pdo, 'items', 'tags', 'VARCHAR(255) DEFAULT NULL AFTER `book_language`');
        mig_add_column_if_missing($pdo, 'items', 'faqs_json', 'MEDIUMTEXT DEFAULT NULL AFTER `tags`');

        // 5. Hub pages table (Topic / Exam Pillar landing pages)
        mig_create_table_if_missing($pdo, 'hubs', "
            CREATE TABLE `hubs` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `slug` VARCHAR(160) NOT NULL,
                `title_bn` VARCHAR(220) NOT NULL,
                `title_en` VARCHAR(220) NOT NULL,
                `intro_bn` MEDIUMTEXT DEFAULT NULL,
                `intro_en` MEDIUMTEXT DEFAULT NULL,
                `guide_id` INT UNSIGNED DEFAULT NULL,
                `featured_item_ids` VARCHAR(255) DEFAULT NULL,
                `featured_folder_ids` VARCHAR(255) DEFAULT NULL,
                `faqs_json` MEDIUMTEXT DEFAULT NULL,
                `seo_title_bn` VARCHAR(220) DEFAULT NULL,
                `seo_title_en` VARCHAR(220) DEFAULT NULL,
                `seo_description_bn` VARCHAR(320) DEFAULT NULL,
                `seo_description_en` VARCHAR(320) DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_hubs_slug` (`slug`),
                KEY `idx_hubs_active` (`is_active`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 6. Editorial FAQs table (scoped to global/guide/folder/hub)
        mig_create_table_if_missing($pdo, 'editorial_faqs', "
            CREATE TABLE `editorial_faqs` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `scope_type` ENUM('global', 'guide', 'folder', 'hub') NOT NULL DEFAULT 'global',
                `scope_id` INT UNSIGNED DEFAULT NULL,
                `question_bn` VARCHAR(300) NOT NULL,
                `answer_bn` TEXT NOT NULL,
                `question_en` VARCHAR(300) NOT NULL,
                `answer_en` TEXT NOT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_ed_faq_scope` (`scope_type`, `scope_id`, `is_active`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 7. Expand slug_history to support hub slugs
        mig_modify_column_if_exists(
            $pdo,
            'slug_history',
            'entity_type',
            "ENUM('folder', 'item', 'product', 'guide', 'author', 'hub') NOT NULL"
        );

        // 8. Editorial E-E-A-T Trust Pages Settings
        $trustDefaults = [
            'legal_editorial_bn' => '<h2>সম্পাদকীয় নীতিমালা (Editorial Policy)</h2><p>Bookexa-এর সকল পরীক্ষার গাইড, সিলেবাস বিশ্লেষণ ও বই পরিচিতি বাংলাদেশের সর্বশেষ জাতীয় শিক্ষাক্রম, পিএসসি ও বিশ্ববিদ্যালয় ভর্তি বিজ্ঞপ্তির আলোকে যাচাই করে প্রকাশ করা হয়।</p><h3>উৎস ও তথ্য যাচাই</h3><p>আমাদের লেখক ও বিষয়ভিত্তিক সম্পাদকেরা প্রতিটি গাইড প্রকাশের আগে মূল প্রশ্নপত্র, সরকারি সার্কুলার এবং স্বীকৃত পাঠ্যবইয়ের সাথে তথ্য মিলিয়ে দেখেন। কোনো বাণিজ্যিক প্রচারণাকে আমরা বস্তুনিষ্ঠ পরামর্শের ওপরে স্থান দিই না।</p><h3>নিয়মিত হালনাগাদ</h3><p>পরীক্ষার নম্বরবণ্টন বা সিলেবাস পরিবর্তন হলে প্রবন্ধের "সর্বশেষ হালনাগাদ" তারিখসহ প্রাসঙ্গিক অংশ সংশোধন করা হয়।</p>',
            'legal_editorial_en' => '<h2>Editorial Policy</h2><p>Every exam guide, syllabus breakdown, and study recommendation published on Bookexa is written and reviewed against official Bangladesh exam notices (BPSC, NCTB, NCTE, university admission circulars, and recognized textbooks).</p><h3>Accuracy &amp; Independence</h3><p>Our contributors verify marks distributions, question patterns, and reading lists directly from primary sources. Paid partnerships or book listings never influence editorial study advice.</p><h3>Ongoing Updates</h3><p>When syllabi or exam formats change, guides are updated promptly with an accurate "Last Updated" timestamp shown to readers.</p>',
            'legal_review_policy_bn' => '<h2>আমরা কীভাবে বই পর্যালোচনা করি (How We Review Books)</h2><p>শিক্ষার্থী ও পাঠকদের সঠিক বই নির্বাচনে সাহায্য করতে Bookexa একটি স্বচ্ছ মূল্যায়ন কাঠামো অনুসরণ করে।</p><ul><li><strong>সিলেবাস সামঞ্জস্য:</strong> সাম্প্রতিক পরীক্ষার প্রশ্ন ও পাঠ্যসূচি কতটা কাভার করেছে তা পরীক্ষা করা হয়।</li><li><strong>নির্ভুলতা ও ব্যাখ্যা:</strong> বানান, তথ্যসূত্র এবং গণিত ও বিজ্ঞানের সমাধানগুলোর নির্ভুলতা যাচাই করা হয়।</li><li><strong>পড়ার উপযোগিতা:</strong> ছাপার মান, বিন্যাস, ফ্রি স্যাম্পল পেজ এবং শিক্ষার্থীবান্ধব ভাষার প্রাঞ্জলতা গুরুত্ব পায়।</li></ul><p>বইয়ের পাতায় প্রদর্শিত পাঠক রিভিউগুলো প্রকৃত পাঠক ও ক্রেতাদের অনুমোদিত মতামত থেকে সংকলিত।</p>',
            'legal_review_policy_en' => '<h2>How We Review Books</h2><p>To help students choose reliable preparation resources, Bookexa evaluates digital PDFs and printed editions using consistent, transparent criteria:</p><ul><li><strong>Syllabus Alignment:</strong> Coverage of the latest exam syllabus and recent question trends.</li><li><strong>Factual Accuracy:</strong> Verification of answers, explanations, formulas, and official citations.</li><li><strong>Clarity &amp; Format:</strong> Readability in Bangla and English, logical chapter structure, and free sample transparency.</li></ul><p>Reader ratings displayed on book pages come strictly from verified readers and approved reviews.</p>',
            'legal_corrections_bn' => '<h2>সংশোধনী নীতি (Corrections Policy)</h2><p>তথ্যগত নির্ভুলতা আমাদের সর্বোচ্চ অগ্রাধিকার। কোনো গাইড, প্রশ্নের উত্তর বা বইয়ের তথ্যে ভুল ধরা পড়লে আমাদের সম্পাদকীয় দল তা দ্রুত যাচাই করে সংশোধন করে।</p><h3>ভুল রিপোর্ট করার নিয়ম</h3><p>আপনি যদি কোনো তারিখ, উদ্ধৃতি বা গাণিতিক তথ্যে অসংগতি দেখেন, অনুগ্রহ করে <a href="/contact">যোগাযোগ পাতায়</a> সংশ্লিষ্ট গাইডের লিংকসহ বিস্তারিত জানান। যাচাই সম্পন্ন হলে ২৪–৭২ ঘণ্টার মধ্যে সংশোধন করা হয় এবং উল্লেখযোগ্য পরিবর্তনের ক্ষেত্রে পাতায় সংশোধনী টীকা যুক্ত করা হয়।</p>',
            'legal_corrections_en' => '<h2>Corrections Policy</h2><p>Accuracy is core to student trust. Whenever a factual error, outdated exam date, or broken citation is identified in a guide or catalog description, we investigate and correct it promptly.</p><h3>Report an Error</h3><p>If you spot an error, please send the page URL and supporting reference via our <a href="/contact">Contact page</a>. Verified corrections are applied within 24–72 hours with an updated timestamp.</p>',
        ];
        foreach ($trustDefaults as $k => $v) {
            mig_insert_setting_if_missing($pdo, $k, $v);
        }

        // 9. Seed 1 editorial Author, 6 genuine exam/study Guides, 6 real Reader FAQs, and 3 Topic Hubs if empty
        $gCnt = (int) $pdo->query('SELECT COUNT(*) FROM `guides`')->fetchColumn();
        if ($gCnt === 0) {
            $edAuthor = $pdo->query("SELECT id FROM `authors` WHERE slug = 'bookexa-editorial' LIMIT 1")->fetchColumn();
            if (!$edAuthor) {
                $stAu = $pdo->prepare(
                    "INSERT IGNORE INTO `authors` (`slug`, `name_bn`, `name_en`, `expertise_bn`, `expertise_en`, `bio_bn`, `bio_en`, `is_active`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
                );
                $stAu->execute([
                    'bookexa-editorial',
                    'ড. মাহমুদুল হাসান ও সম্পাদনা পর্ষদ',
                    'Dr. Mahmudul Hasan & Editorial Team',
                    'বিসিএস, বিশ্ববিদ্যালয় ভর্তি ও মাধ্যমিক-উচ্চমাধ্যমিক শিক্ষাক্রম বিশেষজ্ঞ',
                    'BCS, University Admission & NCTB Curriculum Specialists',
                    'ঢাকা বিশ্ববিদ্যালয়ের প্রাক্তন শিক্ষার্থী ও অভিজ্ঞ একাডেমিক সম্পাদকদের নিয়ে গঠিত Bookexa গবেষক দল, যারা পরীক্ষার্থীদের জন্য নির্ভরযোগ্য স্টাডি রোডম্যাপ ও বই বিশ্লেষণ তৈরি করেন।',
                    'A team of Dhaka University alumni and academic editors compiling practical exam roadmaps, syllabus analyses, and verified book recommendations for learners across Bangladesh.',
                ]);
                $edAuthor = (int) $pdo->lastInsertId();
                if ($edAuthor <= 0) {
                    $edAuthor = (int) $pdo->query("SELECT id FROM `authors` WHERE slug = 'bookexa-editorial' LIMIT 1")->fetchColumn();
                }
            } else {
                $edAuthor = (int) $edAuthor;
            }

            $guidesSeed = [
                [
                    'slug' => 'bcs-preliminary-complete-study-plan',
                    'cat_slug' => 'bcs', 'cat_bn' => 'বিসিএস প্রস্তুতি', 'cat_en' => 'BCS Preparation',
                    'tags' => 'bcs,preliminary,bangla,english,gk',
                    'mins' => 6,
                    'title_bn' => 'বিসিএস প্রিলিমিনারি ২০০ নম্বরের পূর্ণাঙ্গ প্রস্তুতি কৌশল ও রুটিন',
                    'title_en' => 'Complete 200-Mark BCS Preliminary Study Plan & Strategy',
                    'sum_bn' => 'বাংলা, ইংরেজি, সাধারণ জ্ঞান, গণিত ও বিজ্ঞান—কোন বিষয়ে কত সময় দেবেন এবং কীভাবে নেগেটিভ মার্কিং এড়াবেন তার বাস্তবসম্মত নির্দেশিকা।',
                    'sum_en' => 'A realistic subject-wise strategy covering Bangla, English, General Knowledge, Math, and Science while avoiding negative marking traps.',
                    'body_bn' => '<h2>বিসিএস প্রিলিমিনারি নম্বরবণ্টন বোঝা কেন জরুরি</h2><p>বাংলাদেশ সরকারি কর্ম কমিশনের (পিএসসি) অধীনে ২০০ নম্বরের প্রিলিমিনারি পরীক্ষায় প্রতিটি ভুল উত্তরের জন্য ০.৫০ নম্বর কাটা যায়। তাই শুধু বেশি পড়া নয়, বরং ১০টি বিষয়ের গুরুত্ব অনুযায়ী সময় ভাগ করে পড়া সবচেয়ে কার্যকর কৌশল।</p><h3>উচ্চ-নম্বরদায়ী বিষয়গুলোতে অগ্রাধিকার</h3><p>বাংলা সাহিত্য ও ব্যাকরণ (৩৫), ইংরেজি সাহিত্য ও গ্রামার (৩৫), বাংলাদেশ বিষয়াবলি (৩০) এবং আন্তর্জাতিক বিষয়াবলি (২০)—এই চারটি অংশে মোট ১২০ নম্বর থাকে। শুরুতে ১০ম থেকে ৪৬তম বিসিএস-এর বিগত প্রশ্নগুলো ব্যাখ্যাসহ সমাধান করলে প্রশ্নের ধারা স্পষ্ট হয়।</p><h2>গণিত, মানসিক দক্ষতা ও বিজ্ঞানে পূর্ণ নম্বর তোলার উপায়</h2><p>গণিত (১৫), মানসিক দক্ষতা (১৫) এবং দৈনন্দিন বিজ্ঞান ও কম্পিউটার (৩০)—এই ৬০ নম্বর অনুশীলননির্ভর। প্রতিদিন অন্তত ৩০ মিনিট অষ্টম-নবম শ্রেণির বোর্ড গণিত ও বিগত প্রশ্ন অনুশীলন করলে পরীক্ষার হলে আত্মবিশ্বাস অটুট থাকে।</p><h3>রিভিশন ও মডেল টেস্ট</h3><p>সপ্তাহের ৫ দিন নতুন অধ্যায় পড়ে ৬ষ্ঠ দিনে পুরো সপ্তাহের পড়া রিভিশন দিন। ৭ম দিনে ঘড়ি ধরে ২০০ নম্বরের ওএমআর মডেল টেস্ট দিলে সময় ব্যবস্থাপনা আয়ত্তে আসে। আমাদের <a href="/pdf">পিডিএফ সংগ্রহ</a> ও <a href="/editorial-policy">সম্পাদকীয় নীতিমালা</a> অনুসারে সাজানো বইগুলো থেকে নিজের উপযোগী নোট বেছে নিন।</p>',
                    'body_en' => '<h2>Understanding the 200-Mark BCS Preliminary Structure</h2><p>Under BPSC rules, every wrong answer in the 200-mark MCQ exam deducts 0.50 marks. Smart time allocation across the ten syllabus subjects matters just as much as total study hours.</p><h3>Prioritize High-Weightage Core Subjects</h3><p>Bangla (35), English (35), Bangladesh Affairs (30), and International Affairs (20) account for 120 marks. Begin by analyzing past BCS papers from the 10th to 46th cycles to spot recurring topic patterns before moving to deep reference guides.</p><h2>Securing Full Marks in Math, Mental Ability &amp; Science</h2><p>Daily practice of Class 8-10 NCTB Math problems alongside General Science and ICT helps turn these 60 marks into reliable scoring ground.</p><h3>Weekly Revision &amp; Timed Practice</h3><p>Study new topics five days a week, revise notes on day six, and take one full 2-hour mock exam on day seven. Explore organized study PDFs in our <a href="/pdf">PDF Library</a> or check <a href="/guides">all study guides</a>.</p>',
                ],
                [
                    'slug' => 'ssc-board-exam-last-90-days-roadmap',
                    'cat_slug' => 'ssc', 'cat_bn' => 'এসএসসি প্রস্তুতি', 'cat_en' => 'SSC Exam',
                    'tags' => 'ssc,science,math,bangla,board-exam',
                    'mins' => 5,
                    'title_bn' => 'এসএসসি পরীক্ষার শেষ ৯০ দিনের কার্যকর রিভিশন ও বোর্ড প্রশ্ন সমাধান কৌশল',
                    'title_en' => 'SSC Board Exam Last 90 Days Revision & Past Paper Strategy',
                    'sum_bn' => 'নবম-দশম শ্রেণির বোর্ড পরীক্ষার আগে সৃজনশীল (CQ) ও বহুনির্বাচনি (MCQ) অংশে এ-প্লাস নিশ্চিত করার অধ্যায়ভিত্তিক পরিকল্পনা।',
                    'sum_en' => 'Chapter-by-chapter 90-day revision framework for SSC Science, Commerce, and Humanities students targeting A+ in CQ and MCQ.',
                    'body_bn' => '<h2>শেষ তিন মাসে বই পড়ার সঠিক ক্রম</h2><p>এসএসসি পরীক্ষায় ভালো ফলাফলের মূল ভিত্তি হলো এনসিটিবি প্রণীত মূল পাঠ্যবই। গাইড বা টেস্ট পেপার দেখার আগে প্রতিটি অধ্যায়ের মূল সংজ্ঞা, সূত্র ও উদাহরণগুলো পরিষ্কারভাবে বুঝে নেওয়া প্রয়োজন।</p><h3>সৃজনশীল (CQ) উত্তর লেখার কৌশল</h3><p>ক (জ্ঞানমূলক) ও খ (অনুধাবনমূলক) প্রশ্নের উত্তর সংক্ষিপ্ত ও নির্ভুল হলে পরীক্ষক গ ও ঘ অংশে সহজেই পূর্ণ নম্বর দেন। পদার্থবিজ্ঞান, রসায়ন ও জীববিজ্ঞানে প্রাসঙ্গিক চিত্র পেন্সিল দিয়ে পরিষ্কারভাবে আঁকা ভালো নম্বর পেতে সাহায্য করে।</p><h2>বোর্ড প্রশ্ন ও টেস্ট পেপার অনুশীলন</h2><p>গত ৫ বছরের সকল শিক্ষা বোর্ডের প্রশ্ন সমাধান করলে অধ্যায়ভিত্তিক গুরুত্বপূর্ণ টপিকগুলো চোখে পড়ে। আমাদের <a href="/pdf">একাডেমিক পিডিএফ ফোল্ডারে</a> বিষয়ভিত্তিক নোট ও অধ্যায়সারাংশ পাবেন।</p>',
                    'body_en' => '<h2>Why NCTB Textbooks Come First in the Final 90 Days</h2><p>Before jumping into test papers, lock down core definitions, formulas, and textbook worked examples across every chapter. Board questions consistently reward clear conceptual foundations.</p><h3>Structuring Creative Questions (CQ)</h3><p>Keep Knowledge (A) and Comprehension (B) answers crisp and accurate. In Physics, Chemistry, and Biology, clean labeled diagrams drawn in pencil substantially lift Application and Higher-Order marks.</p><h2>Practicing Past Board Questions</h2><p>Solve five years of all education board papers under timed conditions. Browse organized subject notes inside our <a href="/pdf">PDF Collections</a> and review our <a href="/how-we-review">book review standards</a>.</p>',
                ],
                [
                    'slug' => 'hsc-science-physics-chemistry-notes-guide',
                    'cat_slug' => 'hsc', 'cat_bn' => 'এইচএসসি বিজ্ঞান', 'cat_en' => 'HSC Science',
                    'tags' => 'hsc,physics,chemistry,biology,science',
                    'mins' => 5,
                    'title_bn' => 'এইচএসসি বিজ্ঞান: পদার্থবিজ্ঞান ও রসায়ন সূত্র মনে রাখার সহজ পদ্ধতি',
                    'title_en' => 'HSC Science Guide: Mastering Physics & Chemistry Formulas',
                    'sum_bn' => 'এইচএসসি প্রথম ও দ্বিতীয় পত্রের জটিল গাণিতিক সমস্যা ও জৈব রসায়নের বিক্রিয়া মনে রাখার ব্যবহারিক কৌশল।',
                    'sum_en' => 'Practical techniques for remembering Physics derivations, Chemistry equations, and Organic Chemistry mechanisms cleanly.',
                    'body_bn' => '<h2>মুখস্থ নয়, এককের বিশ্লেষণ দিয়ে সূত্র শিখুন</h2><p>এইচএসসি পদার্থবিজ্ঞানে অধ্যায়ভিত্তিক শতাধিক সূত্র মুখস্থ করতে গেলে পরীক্ষার হলে বিভ্রান্তি তৈরি হয়। প্রতি অধ্যায়ের শেষে একটি দুই পৃষ্ঠার ফর্মুলা শিট তৈরি করে প্রতিটি রাশির এসআই একক লিখে রাখুন।</p><h3>জৈব রসায়নের বিক্রিয়া ধাপে ধাপে সাজান</h3><p>অ্যালকেন থেকে অ্যালকোহল, অ্যালডিহাইড ও কার্বক্সিলিক অ্যাসিডে রূপান্তরের একটি বড় ফ্লো-চার্ট পড়ার টেবিলের সামনে রাখলে প্রতিদিন চোখে পড়ায় বিক্রিয়াগুলো স্বাভাবিকভাবে মনে থাকে।</p><h2>গাণিতিক সমস্যা ও স্যাম্পল নোট</h2><p>প্রতিদিন কমপক্ষে ৫টি ভিন্ন টাইপের গাণিতিক অঙ্ক খাতায় কষে সমাধান করুন। <a href="/guides">সকল স্টাডি গাইড</a> এবং <a href="/pdf">এইচএসসি পিডিএফ সংগ্রহ</a> নিয়মিত দেখতে পারেন।</p>',
                    'body_en' => '<h2>Learn Physics Equations via Dimensional Analysis</h2><p>Instead of rote memorization, build a two-page chapter formula sheet noting each variable and its SI unit. Checking dimensions prevents sign and power mistakes during exams.</p><h3>Mapping Organic Chemistry Reactions</h3><p>Draw a single interconversion flow-chart linking alkanes, alcohols, aldehydes, and carboxylic acids with catalysts and temperatures clearly marked.</p><h2>Daily Numerical Problem Practice</h2><p>Write out five varied numericals by hand each day. Visit our <a href="/pdf">Academic PDFs</a> and <a href="/guides">editorial study guides</a> for structured notes.</p>',
                ],
                [
                    'slug' => 'medical-admission-biology-memorization-guide',
                    'cat_slug' => 'medical-admission', 'cat_bn' => 'মেডিকেল ভর্তি', 'cat_en' => 'Medical Admission',
                    'tags' => 'medical,admission,biology,gk,hsc',
                    'mins' => 6,
                    'title_bn' => 'মেডিকেল ভর্তি প্রস্তুতি: জীববিজ্ঞান ও সাধারণ জ্ঞানের খুঁটিনাটি আয়ত্তের উপায়',
                    'title_en' => 'Medical Admission Prep: High-Yield Biology & GK Strategy',
                    'sum_bn' => 'মেডিকেল ভর্তি পরীক্ষায় ১০০ নম্বরের এমসিকিউতে জীববিজ্ঞানের ৩০ ও রসায়নের ২৫ নম্বর নিশ্চিত করার নিখুঁত কৌশল।',
                    'sum_en' => 'High-accuracy preparation roadmap for the 100-mark Bangladesh Medical Admission test focusing on Biology, Chemistry, and English.',
                    'body_bn' => '<h2>মূল বইয়ের লাইন-বাই-লাইন পড়ার গুরুত্ব</h2><p>সরকারি মেডিকেল কলেজ ভর্তি পরীক্ষায় ৩০ নম্বর থাকে জীববিজ্ঞান (উদ্ভিদবিজ্ঞান ও প্রাণিবিজ্ঞান) অংশে। বিগত বছরগুলোতে দেখা গেছে, প্রায় সব প্রশ্নই মূল পাঠ্যবইয়ের ছক, বোল্ড করা শব্দ ও ব্যতিক্রমী উদাহরণ থেকে আসে।</p><h3>উদ্ভিদবিজ্ঞান ও প্রাণিবিজ্ঞানের তুলনামূলক ছক</h3><p>রক্তকণিকা, এনজাইম, হরমোন ও কঙ্কালতন্ত্রের সংখ্যাগুলো আলাদা কাগজে তুলনামূলক ছক করে পড়লে নেগেটিভ মার্কিং অনেক কমে আসে।</p><h2>রসায়ন, পদার্থ, ইংরেজি ও সাধারণ জ্ঞান</h2><p>পরিবেশ রসায়ন, পরিমাণগত রসায়ন ও জৈব রসায়নের বৈশিষ্ট্যমূলক বিক্রিয়াগুলো নিয়মিত অনুশীলন করুন। আমাদের <a href="/hub/medical-admission">মেডিকেল ভর্তি হাব</a> এবং <a href="/authors">লেখক ও সম্পাদনা পর্ষদের</a> পাতা দেখুন।</p>',
                    'body_en' => '<h2>Line-by-Line Reading of Biology Textbooks</h2><p>Biology carries 30 of the 100 MCQ marks in the MBBS admission exam. Almost every question tests textbook tables, bolded terms, scientific names, and physiological exceptions.</p><h3>Use Comparison Tables to Stop Negative Marks</h3><p>Group enzymes, hormones, vitamins, and cranial nerves into side-by-side summary sheets so similar options never cause confusion under exam pressure.</p><h2>Balanced Coverage of Chemistry &amp; English</h2><p>Pair Biology drills with daily Chemistry numericals and standard medical English vocabulary. Explore our <a href="/hub/medical-admission">Medical Admission Hub</a> and <a href="/pdf">PDF Library</a>.</p>',
                ],
                [
                    'slug' => 'university-dhaka-ka-unit-admission-strategy',
                    'cat_slug' => 'university-admission', 'cat_bn' => 'বিশ্ববিদ্যালয় ভর্তি', 'cat_en' => 'University Admission',
                    'tags' => 'du,university,admission,mcq,written',
                    'mins' => 5,
                    'title_bn' => 'ঢাকা বিশ্ববিদ্যালয় ভর্তি প্রস্তুতি: এমসিকিউ ও লিখিত অংশের সময় ব্যবস্থাপনা',
                    'title_en' => 'Dhaka University Admission: Balancing MCQ & Written Sections',
                    'sum_bn' => 'ক্যালকুলেটর ছাড়া দ্রুত গাণিতিক হিসাব এবং লিখিত অংশে পরিচ্ছন্ন উপস্থাপনার কার্যকর অনুশীলন গাইড।',
                    'sum_en' => 'Practical guide to fast non-calculator math and structured short answers for Dhaka University Ka/Kha unit admission tests.',
                    'body_bn' => '<h2>ক্যালকুলেটর ছাড়া দ্রুত হিসাবের দক্ষতা</h2><p>ঢাকা বিশ্ববিদ্যালয়ের ভর্তি পরীক্ষায় ক্যালকুলেটর ব্যবহার নিষিদ্ধ থাকায় ভগ্নাংশ, লগারিদম ও বর্গমূল-ঘনমূলের আসন্ন মান দ্রুত বের করার অভ্যাস গড়ে তুলতে হয়।</p><h3>লিখিত (Written) অংশের ৪০ নম্বর যেভাবে গুছিয়ে লিখবেন</h3><p>লিখিত প্রশ্নের জন্য নির্ধারিত জায়গা খুবই সীমিত থাকে। তাই অপ্রয়োজনীয় ভূমিকা না লিখে মূল সমীকরণ, একক এবং যুক্তিযুক্ত দুই-তিন বাক্যে উত্তর শেষ করা উচিত।</p><h2>প্রশ্নব্যাংক বিশ্লেষণ</h2><p>বিগত ১৫ বছরের প্রশ্নব্যাংক অনুশীলন এবং নিয়মিত সাপ্তাহিক পরীক্ষা আপনার র‍্যাংক এগিয়ে রাখবে। আরও পড়ুন আমাদের <a href="/guides">অন্যান্য স্টাডি গাইড</a> এবং <a href="/how-we-review">বই যাচাই নীতি</a>।</p>',
                    'body_en' => '<h2>Mastering Fast Calculations Without a Calculator</h2><p>Since calculators are prohibited in DU admission tests, train daily on fractional approximations, standard squares, powers of two, and common log values.</p><h3>Scoring Well in the Written Section</h3><p>Answer boxes have strict line limits. Skip lengthy introductions and write crisp equations, labeled steps, and concise justifications.</p><h2>Question Bank &amp; Reference Notes</h2><p>Work through 15 years of past questions twice before exam month. Browse organized prep books inside our <a href="/pdf">PDF Directory</a>.</p>',
                ],
                [
                    'slug' => 'how-to-read-digital-pdf-books-without-eye-strain',
                    'cat_slug' => 'study-tips', 'cat_bn' => 'পড়াশোনার কৌশল', 'cat_en' => 'Study Tips',
                    'tags' => 'study-tips,digital-reading,notes,bookmarks',
                    'mins' => 4,
                    'title_bn' => 'ডিজিটাল পিডিএফ বই থেকে দীর্ঘক্ষণ পড়েও চোখ ও মনোযোগ ভালো রাখার বিজ্ঞানসম্মত উপায়',
                    'title_en' => 'How to Study from Digital PDFs Effectively Without Eye Strain',
                    'sum_bn' => 'নাইট মোড, ২০-২০-২০ নিয়ম, অ্যাক্টিভ নোটস এবং বুকমার্ক ব্যবহার করে অনলাইন রিডিংয়ে সর্বোচ্চ মনে রাখার কৌশল।',
                    'sum_en' => 'Science-backed study habits using dark mode, the 20-20-20 rule, active recall notes, and bookmarks when reading online PDFs.',
                    'body_bn' => '<h2>স্ক্রিন উজ্জ্বলতা ও ২০-২০-২০ নিয়ম</h2><p>ডিজিটাল রিডারে টানা পড়ার সময় প্রতি ২০ মিনিট পর ২০ সেকেন্ডের জন্য কমপক্ষে ২০ ফুট দূরের কোনো বস্তুর দিকে তাকান। রাতে পড়ার ক্ষেত্রে Bookexa রিডারের ডার্ক মোড ও ক্রিম ব্যাকগ্রাউন্ড চোখের চাপ কমায়।</p><h3>প্যাসিভ রিডিং নয়, অ্যাক্টিভ রিকল চর্চা করুন</h3><p>শুধু পৃষ্ঠা উল্টে যাওয়ার বদলে প্রতিটি অধ্যায় শেষে বই বন্ধ করে ৩টি মূল পয়েন্ট নিজ ভাষায় খাতায় লিখুন।</p><h2>বুকমার্ক ও প্রগ্রেস ট্র্যাকিং</h2><p>রিডারের বিল্ট-ইন বুকমার্ক দিয়ে গুরুত্বপূর্ণ সূত্র ও অনুচ্ছেদ চিহ্নিত করে রাখুন যাতে পরীক্ষার আগের রাতে মুহূর্তেই রিভিশন দেওয়া যায়। আরও সহায়তার জন্য <a href="/contact">সাপোর্ট পাতা</a> বা <a href="/pdf">পিডিএফ লাইব্রেরি</a> দেখুন।</p>',
                    'body_en' => '<h2>Adjust Display Contrast &amp; Apply the 20-20-20 Rule</h2><p>Every 20 minutes, look 20 feet away for 20 seconds to relax eye focus. Use Bookexa dark theme during late-night study sessions to reduce glare.</p><h3>Use Active Recall Instead of Passive Scrolling</h3><p>At the end of every chapter, close the screen and write three key takeaways from memory before verifying against the text.</p><h2>Leverage Bookmarks for Rapid Exam Revision</h2><p>Tag difficult theorems and summaries with reader bookmarks so finals-week revision takes minutes instead of hours. Visit our <a href="/pdf">PDF Library</a> to start reading.</p>',
                ],
            ];

            $insG = $pdo->prepare(
                'INSERT INTO `guides` (`slug`, `category_slug`, `category_bn`, `category_en`, `tags`, `title_bn`, `title_en`, `summary_bn`, `summary_en`, `body_bn`, `body_en`, `author_name`, `author_id`, `reading_minutes`, `is_active`, `content_hash`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
            );
            foreach ($guidesSeed as $gs) {
                $hash = function_exists('content_normalized_hash')
                    ? content_normalized_hash($gs['body_bn'], $gs['body_en'])
                    : hash('sha256', mb_strtolower(trim(strip_tags($gs['body_bn'])) . '|' . trim(strip_tags($gs['body_en'])), 'UTF-8'));
                $insG->execute([
                    $gs['slug'], $gs['cat_slug'], $gs['cat_bn'], $gs['cat_en'], $gs['tags'],
                    $gs['title_bn'], $gs['title_en'], $gs['sum_bn'], $gs['sum_en'],
                    $gs['body_bn'], $gs['body_en'],
                    'Dr. Mahmudul Hasan', $edAuthor, $gs['mins'], $hash,
                ]);
            }
        }

        $faqCnt = (int) $pdo->query('SELECT COUNT(*) FROM `editorial_faqs`')->fetchColumn();
        if ($faqCnt === 0) {
            $faqsSeed = [
                [
                    'এসএসসি ও বিসিএস প্রস্তুতিতে পাঠ্যবই নাকি গাইড—কোনটি আগে পড়া উচিত?',
                    'সর্বদা মূল পাঠ্যবই (এনসিটিবি) ও মূল কনসেপ্ট দিয়ে শুরু করা উচিত। মৌলিক ধারণা পরিষ্কার হওয়ার পর বিগত প্রশ্ন বিশ্লেষণ ও অনুশীলনের জন্য সাজানো গাইড ও মডেল টেস্ট ব্যবহার করলে সর্বোচ্চ ফল পাওয়া যায়।',
                    'Should I start exam preparation with textbooks or guidebooks?',
                    'Always begin with official core textbooks (NCTB) to build clear fundamentals. Once concepts are solid, use structured guides and past-paper solutions for speed and exam-pattern familiarity.',
                ],
                [
                    'বিসিএস প্রিলিমিনারিতে নেগেটিভ মার্কিং কীভাবে কমানো যায়?',
                    'প্রতিটি ভুল উত্তরে ০.৫০ নম্বর কাটা যায় বলে যেসব প্রশ্নে দুটি অপশন বাদ দেওয়া সম্ভব নয়, সেগুলো এড়িয়ে যাওয়া নিরাপদ। নিয়মিত ঘড়ি ধরে ওএমআর মডেল টেস্ট অনুশীলন করলে অনুমাননির্ভর ভুল অনেক কমে আসে।',
                    'How can I minimize negative marking in BCS Preliminary MCQs?',
                    'Because each wrong guess deducts 0.50 marks, avoid blind guessing unless you can confidently eliminate at least two options. Timed weekly mock tests sharpen risk judgment.',
                ],
                [
                    'Bookexa-তে ডিজিটাল পিডিএফ বই আনলক হওয়ার পর কীভাবে পড়তে হয়?',
                    'পেমেন্ট যাচাই সম্পন্ন হলে বা ফ্রি বই বেছে নিলে বইটি আপনার "আমার লাইব্রেরি" পাতায় যুক্ত হয়। কোনো আলাদা সফটওয়্যার ছাড়াই মোবাইল বা কম্পিউটার ব্রাউজার থেকে নিরাপদ রিডারে তাৎক্ষণিকভাবে পড়া ও বুকমার্ক করা যায়।',
                    'How do I read unlocked PDF books on Bookexa?',
                    'Once an order is verified or a free edition is selected, it opens directly inside My Library in our protected in-browser reader with bookmarks, dark mode, and automatic page resume.',
                ],
                [
                    'কেনার আগে পিডিএফ বইয়ের মান ও সূচিপত্র যাচাই করা যায় কি?',
                    'হ্যাঁ, প্রায় প্রতিটি বইয়ের বিস্তারিত পাতায় নির্ধারিত ফ্রি স্যাম্পল পেজ পড়ার সুযোগ, পৃষ্ঠা সংখ্যা, সংস্করণ এবং যাচাইকৃত পাঠকদের রিভিউ থাকে যাতে আপনি নিশ্চিত হয়ে সংগ্রহ করতে পারেন।',
                    'Can I preview sample pages before purchasing a digital book?',
                    'Yes. Book pages display page counts, edition details, table of contents, approved reader reviews, and free sample pages so you can verify quality before buying.',
                ],
                [
                    'মেডিকেল বা বিশ্ববিদ্যালয় ভর্তি পরীক্ষায় শেষ এক মাসে কী পড়া উচিত?',
                    'শেষ এক মাসে নতুন অপরিচিত বই না ধরে নিজের তৈরি সংক্ষিপ্ত নোট, ছক, সূত্রের তালিকা এবং বিগত ১০ বছরের প্রশ্নপত্র বারবার রিভিশন দেওয়া সবচেয়ে বেশি কার্যকর।',
                    'What should students focus on during the final month before admission tests?',
                    'Avoid picking up unfamiliar books in the last 30 days. Focus strictly on your personal formula sheets, summary tables, textbook exceptions, and timed revision of past 10-year papers.',
                ],
                [
                    'কোনো গাইড বা তথ্যে ভুল পেলে কীভাবে জানাবো?',
                    'আমাদের যোগাযোগ পাতার মাধ্যমে সংশ্লিষ্ট লিংক ও সঠিক রেফারেন্স পাঠালে সম্পাদনা দল তা ২৪–৭২ ঘণ্টার মধ্যে যাচাই করে হালনাগাদ করে দেয়।',
                    'How can I report a factual correction in a study guide?',
                    'Send the page URL and reference details through our Contact page. Our editorial team reviews and applies verified updates under our Corrections Policy within 24–72 hours.',
                ],
            ];
            $insFaq = $pdo->prepare(
                "INSERT INTO `editorial_faqs` (`scope_type`, `scope_id`, `question_bn`, `answer_bn`, `question_en`, `answer_en`, `sort_order`, `is_active`)
                 VALUES ('global', NULL, ?, ?, ?, ?, ?, 1)"
            );
            foreach ($faqsSeed as $idx => $fq) {
                $insFaq->execute([$fq[0], $fq[1], $fq[2], $fq[3], $idx + 1]);
            }
        }

        // 10. Seed 3 starter Hub Pages referencing seeded guides if empty
        $hubCnt = (int) $pdo->query('SELECT COUNT(*) FROM `hubs`')->fetchColumn();
        if ($hubCnt === 0) {
            $bcsGid = (int) ($pdo->query("SELECT id FROM `guides` WHERE slug = 'bcs-preliminary-complete-study-plan' LIMIT 1")->fetchColumn() ?: 0);
            $sscGid = (int) ($pdo->query("SELECT id FROM `guides` WHERE slug = 'ssc-board-exam-last-90-days-roadmap' LIMIT 1")->fetchColumn() ?: 0);
            $medGid = (int) ($pdo->query("SELECT id FROM `guides` WHERE slug = 'medical-admission-biology-memorization-guide' LIMIT 1")->fetchColumn() ?: 0);
            $hubSeed = [
                ['bcs-preparation', 'বিসিএস প্রস্তুতি হাব', 'BCS Preparation Hub', 'পিএসসি বিসিএস প্রিলিমিনারি ও লিখিত পরীক্ষার পূর্ণাঙ্গ রোডম্যাপ, নির্বাচিত পিডিএফ নোট ও সচরাচর জিজ্ঞাসিত প্রশ্নোত্তর একসাথে।', 'A curated starting hub bringing together BCS study roadmaps, essential books, organized folders, and candidate FAQs.', $bcsGid ?: null, 1],
                ['ssc-notes', 'এসএসসি নোটস ও বোর্ড প্রস্তুতি হাব', 'SSC Notes & Board Prep Hub', 'নবম-দশম শ্রেণির বাংলা, ইংরেজি, গণিত ও বিজ্ঞান বিষয়ের অধ্যায়ভিত্তিক নোট, সৃজনশীল গাইড ও পরীক্ষামূলক প্রশ্ন।', 'Organized Class 9-10 chapter notes, board past papers, and revision schedules in one structured landing hub.', $sscGid ?: null, 2],
                ['medical-admission', 'মেডিকেল ভর্তি প্রস্তুতি হাব', 'Medical Admission Prep Hub', 'জীববিজ্ঞান, রসায়ন, পদার্থ ও সাধারণ জ্ঞান—এমবিবিএস ভর্তি পরীক্ষার নির্ভরযোগ্য বই, গাইড এবং গুরুত্বপূর্ণ প্রশ্নোত্তর।', 'Complete MBBS admission prep center uniting textbook reading guides, reference collections, and high-yield FAQs.', $medGid ?: null, 3],
            ];
            $insH = $pdo->prepare(
                'INSERT INTO `hubs` (`slug`, `title_bn`, `title_en`, `intro_bn`, `intro_en`, `guide_id`, `sort_order`, `is_active`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
            );
            foreach ($hubSeed as $hb) {
                $insH->execute($hb);
            }
        }
    },
];
