<?php
declare(strict_types=1);

return [
    'description' => 'AEO & GEO optimization: short answers, cited sources, guide/topic FAQs with optional links, entity settings, AI crawler policy defaults, and last-reviewed trust metadata',
    'up' => static function (PDO $pdo): void {
        // 1. Short answer blocks + guide sources & FAQs
        mig_add_column_if_missing($pdo, 'guides', 'short_answer_bn', 'TEXT DEFAULT NULL AFTER `summary_en`');
        mig_add_column_if_missing($pdo, 'guides', 'short_answer_en', 'TEXT DEFAULT NULL AFTER `short_answer_bn`');
        mig_add_column_if_missing($pdo, 'guides', 'sources_json', 'MEDIUMTEXT DEFAULT NULL AFTER `related_folder_ids`');
        mig_add_column_if_missing($pdo, 'guides', 'faqs_json', 'MEDIUMTEXT DEFAULT NULL AFTER `sources_json`');

        mig_add_column_if_missing($pdo, 'folders', 'short_answer_bn', 'TEXT DEFAULT NULL AFTER `description_en`');
        mig_add_column_if_missing($pdo, 'folders', 'short_answer_en', 'TEXT DEFAULT NULL AFTER `short_answer_bn`');

        mig_add_column_if_missing($pdo, 'items', 'short_answer_bn', 'TEXT DEFAULT NULL AFTER `description_en`');
        mig_add_column_if_missing($pdo, 'items', 'short_answer_en', 'TEXT DEFAULT NULL AFTER `short_answer_bn`');

        mig_add_column_if_missing($pdo, 'hubs', 'short_answer_bn', 'TEXT DEFAULT NULL AFTER `title_en`');
        mig_add_column_if_missing($pdo, 'hubs', 'short_answer_en', 'TEXT DEFAULT NULL AFTER `short_answer_bn`');

        // 2. Categorized FAQs with optional target link
        mig_add_column_if_missing($pdo, 'editorial_faqs', 'topic', "VARCHAR(80) NOT NULL DEFAULT 'general' AFTER `scope_id`");
        mig_add_column_if_missing($pdo, 'editorial_faqs', 'link_url', 'VARCHAR(255) DEFAULT NULL AFTER `answer_en`');
        mig_add_index_if_missing($pdo, 'editorial_faqs', 'idx_ed_faq_topic', 'KEY `idx_ed_faq_topic` (`topic`, `is_active`, `sort_order`)');

        // 3. Entity clarity, sameAs, last-reviewed date & AI crawler defaults
        $defaultCrawlerPolicy = json_encode([
            'Googlebot'          => 'allow',
            'Bingbot'            => 'allow',
            'OAI-SearchBot'      => 'allow',
            'ChatGPT-User'       => 'allow',
            'Claude-SearchBot'   => 'allow',
            'PerplexityBot'      => 'allow',
            'Google-Extended'    => 'block',
            'GPTBot'             => 'block',
            'ClaudeBot'          => 'block',
            'Applebot-Extended'  => 'block',
            'CCBot'              => 'block',
            'Bytespider'         => 'block',
            'Amazonbot'          => 'block',
            'Meta-ExternalAgent' => 'block',
        ], JSON_UNESCAPED_SLASHES);

        $aeoDefaults = [
            'site_founding_year'      => '2024',
            'seo_sameas_extra'        => '',
            'legal_last_reviewed'     => '2026-11-26',
            'aeo_send_noai_paid'      => '1',
            'aeo_global_noimageai'    => '0',
            'aeo_crawler_policy_json' => $defaultCrawlerPolicy,
        ];
        foreach ($aeoDefaults as $k => $v) {
            mig_insert_setting_if_missing($pdo, $k, $v);
        }

        // 4. Populate concise answer-first blocks + citations on starter guides where missing
        $starterAnswers = [
            'bcs-preliminary-complete-study-plan' => [
                'bn' => 'বিসিএস প্রিলিমিনারি ২০০ নম্বরের পরীক্ষায় উত্তীর্ণ হতে বাংলা ও ইংরেজি (৭০), সাধারণ জ্ঞান (৫০) এবং গণিত-বিজ্ঞান-মানসিক দক্ষতা (৬০) ভাগ করে পড়তে হবে। প্রতিদিন অন্তত ৬ ঘণ্টা নিয়মিত অধ্যয়ন, বিগত ১০ বছরের প্রশ্ন সমাধান ও সাপ্তাহিক ওএমআর মডেল টেস্ট নেগেটিভ মার্কিং কমিয়ে নিরাপদ কাট-অফ নিশ্চিত করে।',
                'en' => 'To clear the 200-mark BCS Preliminary exam, divide study hours across Bangla & English (70), GK (50), and Math/Science/Mental Ability (60). Solve 10th-46th past papers first, revise weekly notes, and take timed OMR mock tests every Friday to control negative marking (-0.50).',
                'sources' => json_encode([
                    ['title' => 'Bangladesh Public Service Commission (BPSC) Exam Notices', 'url' => 'https://www.bpsc.gov.bd'],
                    ['title' => 'NCTB Secondary Textbooks (Classes 8-10)', 'url' => 'https://nctb.gov.bd'],
                ], JSON_UNESCAPED_SLASHES),
            ],
            'ssc-board-exam-last-90-days-roadmap' => [
                'bn' => 'এসএসসি পরীক্ষার শেষ ৯০ দিনে প্রথমে এনসিটিবি পাঠ্যবইয়ের প্রতিটি অধ্যায়ের সূত্র ও সংজ্ঞা ঝালিয়ে নিয়ে গত ৫ বছরের বোর্ড প্রশ্ন সমাধান করা উচিত। ক ও খ অংশ নির্ভুল লিখে গ ও ঘ অংশে প্রাসঙ্গিক চিত্র ও ব্যাখ্যা দিলে এ-প্লাস পাওয়া সহজ হয়।',
                'en' => 'In the final 90 days before SSC board exams, master core NCTB textbook definitions and equations first, then practice 5 years of past education board CQ and MCQ papers under timed conditions with clear labeled pencil diagrams.',
                'sources' => json_encode([
                    ['title' => 'NCTB Curriculum & Textbooks', 'url' => 'https://nctb.gov.bd'],
                ], JSON_UNESCAPED_SLASHES),
            ],
            'hsc-science-physics-chemistry-notes-guide' => [
                'bn' => 'এইচএসসি পদার্থবিজ্ঞান ও রসায়ন আয়ত্তে আনতে মুখস্থের বদলে একক বিশ্লেষণ (Dimensional Analysis) ও ২ পৃষ্ঠার অধ্যায়ভিত্তিক ফর্মুলা শিট তৈরি করুন। জৈব রসায়নের বিক্রিয়াগুলো রূপান্তর ফ্লো-চার্টে সাজিয়ে প্রতিদিন ৫টি করে গাণিতিক সমস্যা সমাধান করুন।',
                'en' => 'Master HSC Physics and Chemistry by creating two-page chapter formula sheets backed by dimensional analysis instead of rote memorization. Map organic reactions cleanly into interconversion charts and solve five numerical problems daily.',
                'sources' => json_encode([
                    ['title' => 'NCTB Higher Secondary Science Syllabus', 'url' => 'https://nctb.gov.bd'],
                ], JSON_UNESCAPED_SLASHES),
            ],
        ];
        $updG = $pdo->prepare('UPDATE `guides` SET `short_answer_bn` = COALESCE(NULLIF(`short_answer_bn`, ""), ?), `short_answer_en` = COALESCE(NULLIF(`short_answer_en`, ""), ?), `sources_json` = COALESCE(`sources_json`, ?) WHERE `slug` = ?');
        foreach ($starterAnswers as $sl => $sa) {
            $updG->execute([$sa['bn'], $sa['en'], $sa['sources'], $sl]);
        }

        // 5. Tag existing FAQs cleanly and add topic FAQs across all 6 /faq topics (payments, reading, devices, refunds, bkash_nagad, secure_reader)
        $pdo->exec("UPDATE `editorial_faqs` SET `topic` = 'reading' WHERE `topic` = 'general' AND (`question_en` LIKE '%read%' OR `question_en` LIKE '%sample%' OR `question_en` LIKE '%book%')");
        $pdo->exec("UPDATE `editorial_faqs` SET `topic` = 'general' WHERE `topic` IS NULL OR `topic` = ''");

        $faqTopicsCheck = (int) $pdo->query("SELECT COUNT(*) FROM `editorial_faqs` WHERE `topic` IN ('payments','bkash_nagad','devices','refunds','secure_reader')")->fetchColumn();
        if ($faqTopicsCheck === 0) {
            $topicFaqs = [
                [
                    'payments',
                    'Bookexa-তে ডিজিটাল PDF বা প্রিন্টেড বইয়ের পেমেন্ট কীভাবে করতে হয়?',
                    'চেকআউটের সময় আপনি bKash, Nagad অথবা Rocket নম্বরে Send Money করে আপনার প্রেরক নম্বর ও Transaction ID (TrxID) জমা দিতে পারেন। প্রিন্টেড বইয়ের ক্ষেত্রে সারা বাংলাদেশে ক্যাশ অন ডেলিভারি (COD) সুবিধাও চালু আছে।',
                    'How can I pay for digital PDFs or printed books on Bookexa?',
                    'At checkout, send money via bKash, Nagad, or Rocket and submit your sender number and TrxID for fast verification. Printed books also support Cash on Delivery (COD) across Bangladesh.',
                    '/checkout',
                ],
                [
                    'bkash_nagad',
                    'বিকাশ বা নগদে পেমেন্ট করার কতক্ষণ পর PDF বই আনলক হয়?',
                    'সঠিক Transaction ID (TrxID) জমা দেওয়ার পর আমাদের সাপোর্ট টিম যাচাই করা মাত্রই বইটি আপনার লাইব্রেরিতে আনলক হয় এবং নোটিফিকেশন ও ইমেইলে জানানো হয়। সাধারণত অফিস সময়ে কয়েক মিনিট থেকে ১ ঘণ্টার মধ্যে যাচাই সম্পন্ন হয়।',
                    'How long does it take to unlock a book after paying with bKash or Nagad?',
                    'Once you submit a valid TrxID, verification typically completes within minutes to one hour during support hours, immediately unlocking the book inside My Library and sending a confirmation alert.',
                    '/orders',
                ],
                [
                    'devices',
                    'একটি Bookexa অ্যাকাউন্ট থেকে কয়টি ডিভাইসে বই পড়া যায়?',
                    'নিরাপত্তা ও কপিরাইট সুরক্ষার জন্য একটি অ্যাকাউন্টে নির্দিষ্ট সংখ্যক (সাধারণত ২টি) বিশ্বস্ত ডিভাইস অনুমোদিত থাকে এবং একসাথে একটি সক্রিয় রিডিং সেশন চলে। প্রোফাইল থেকে আপনি পুরোনো ডিভাইস সরাতে বা পরিবর্তন করতে পারেন।',
                    'How many devices can I use with one Bookexa account?',
                    'Each account supports up to two registered devices with one active reading session at a time to protect author copyright. You can review or unlink old devices anytime from your Devices settings page.',
                    '/devices',
                ],
                [
                    'refunds',
                    'ভুলবশত ডাবল পেমেন্ট হলে বা অর্ডার বাতিল করলে রিফান্ড পাওয়া যায় কি?',
                    'হ্যাঁ। ডিজিটাল PDF আনলক হয়ে যাওয়ার পর সাধারণত রিফান্ড প্রযোজ্য নয়, তবে ভুলবশত ডাবল পেমেন্ট, অকার্যকর ট্রানজ্যাকশন বা প্রিন্টেড বইয়ের ত্রুটি থাকলে ৪৮ ঘণ্টার মধ্যে যোগাযোগ করলে যাচাই সাপেক্ষে রিফান্ড করা হয়।',
                    'What is the refund policy for duplicate payments or physical book orders?',
                    'Unlocked digital PDFs are non-refundable once delivered, while duplicate payments, declined transactions, or damaged physical deliveries qualify for verification and refund when reported within 48 hours.',
                    '/refund-policy',
                ],
                [
                    'secure_reader',
                    'নিরাপদ ব্রাউজার রিডার কীভাবে কাজ করে ও ফ্রি স্যাম্পল কীভাবে পড়া যায়?',
                    'Bookexa-র ইন-ব্রাউজার রিডার প্রতিটি পৃষ্ঠা সরাসরি রেন্ডার করে এবং কপিরাইট সুরক্ষায় পাঠকের নাম, আইডি ও সময়যুক্ত সূক্ষ্ম ওয়াটারমার্ক যুক্ত করে। বইয়ের বিস্তারিত পাতা থেকেই লগইন করে প্রথম কয়েক পৃষ্ঠা ফ্রি স্যাম্পল পড়া যায়।',
                    'How does the secure in-browser reader work and how do free samples open?',
                    'Our protected web reader renders watermarked study pages safely inside any phone or desktop browser with dark mode, zoom, and bookmarks—no extra app required. Eligible titles let you preview free sample pages before purchasing.',
                    '/how-we-review',
                ],
            ];
            $insTf = $pdo->prepare(
                "INSERT INTO `editorial_faqs` (`scope_type`, `scope_id`, `topic`, `question_bn`, `answer_bn`, `question_en`, `answer_en`, `link_url`, `sort_order`, `is_active`)
                 VALUES ('global', NULL, ?, ?, ?, ?, ?, ?, ?, 1)"
            );
            foreach ($topicFaqs as $idx => $tf) {
                $insTf->execute([$tf[0], $tf[1], $tf[2], $tf[3], $tf[4], $tf[5], 20 + $idx]);
            }
        }
    },
];
