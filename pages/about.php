<?php
declare(strict_types=1);
$folderCount = (int) (db_one('SELECT COUNT(*) AS c FROM folders WHERE is_active = 1')['c'] ?? 0);
$itemCount   = (int) (db_one('SELECT COUNT(*) AS c FROM items WHERE is_active = 1')['c'] ?? 0);
$lang        = current_lang();
$siteName    = site_setting('site_name', 'Bookexa');
$founded     = site_setting('site_founding_year', '2024');
$phone       = site_setting('contact_phone', '+880 1700-000001');
$email       = site_setting('contact_email', 'support@bookexa.com');
$addr        = site_setting('contact_address', 'Dhaka, Bangladesh');
$shopOn      = site_setting('shop_enabled', '0') === '1';
$reviewed    = site_setting('legal_last_reviewed', date('Y-m-d'));
?>
<div class="container" style="padding-top:24px;padding-bottom:44px;max-width:960px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(t('nav_about')) ?></span>
    </nav>

    <article class="bx-card" style="margin-top:18px;padding:clamp(20px,3vw,34px);">
        <span class="bx-badge bx-badge-primary" style="margin-bottom:10px;"><?= icon('sparkles') ?> <?= e($siteName) ?></span>
        <h1 class="bx-display" style="margin:0 0 10px;"><?= e(t('about_title')) ?></h1>

        <div class="bx-answer-box">
            <span class="bx-answer-kicker"><?= $lang === 'bn' ? 'সংক্ষিপ্ত পরিচিতি' : 'Entity Overview' ?></span>
            <p>
                <?= $lang === 'bn'
                    ? "{$siteName} হলো {$founded} সালে প্রতিষ্ঠিত বাংলাদেশভিত্তিক একটি দ্বিভাষিক (বাংলা ও ইংরেজি) ডিজিটাল পিডিএফ লাইব্রেরি, পরীক্ষার স্টাডি গাইড ও প্রিন্টেড বইয়ের প্ল্যাটফর্ম। এটি {$addr} থেকে পরিচালিত হয় এবং শিক্ষার্থী ও পাঠকদের নিরাপদ ওয়াটারমার্ক ব্রাউজার রিডার ও হোম ডেলিভারি বুকশপ সেবা প্রদান করে।"
                    : "{$siteName} is a Bangladesh-based bilingual (Bangla and English) digital PDF book library, exam study guide publisher, and online bookstore founded in {$founded} and operating from {$addr}. {$siteName} provides a watermarked in-browser PDF reader alongside physical printed book delivery across Bangladesh." ?>
            </p>
        </div>

        <div class="bx-prose" style="max-width:none;">
            <h2><?= $lang === 'bn' ? 'আমরা কী সেবা প্রদান করি ও ফরম্যাটসমূহ' : 'What We Offer & Supported Formats' ?></h2>
            <ul>
                <li>
                    <strong><?= $lang === 'bn' ? 'ডিজিটাল পিডিএফ / ই-বুক:' : 'Digital PDF Editions:' ?></strong>
                    <?= $lang === 'bn'
                        ? 'ফোল্ডার অনুযায়ী সাজানো একাডেমিক নোট, বিসিএস, এসএসসি, এইচএসসি, মেডিকেল ও বিশ্ববিদ্যালয় ভর্তি বই—যেগুলো সরাসরি মোবাইল ও ডেস্কটপ ব্রাউজারে ওয়াটারমার্ক সুরক্ষিত রিডারে পড়া যায় (কোনো আলাদা সফটওয়্যার প্রয়োজন হয় না)।'
                        : 'Organized topic folders for Academic, BCS, SSC, HSC, Medical, and University admission PDFs rendered directly inside our watermark-protected in-browser reader with dark mode, search, and bookmarks.' ?>
                </li>
                <?php if ($shopOn): ?>
                    <li>
                        <strong><?= $lang === 'bn' ? 'প্রিন্টেড বই (শপ):' : 'Printed Physical Books:' ?></strong>
                        <?= $lang === 'bn'
                            ? 'সারা বাংলাদেশের জেলায় জেলায় দ্রুত কুরিয়ার হোম ডেলিভারিসহ ছাপানো সংস্করণের বই অর্ডার সুবিধা।'
                            : 'Original printed books delivered across Dhaka and every district in Bangladesh with order tracking.' ?>
                    </li>
                <?php endif; ?>
                <li>
                    <strong><?= $lang === 'bn' ? 'স্টাডি গাইড ও লেখক পরিচিতি:' : 'Editorial Study Guides & Authors:' ?></strong>
                    <?= $lang === 'bn'
                        ? 'অভিজ্ঞ বিষয়ভিত্তিক লেখক ও সম্পাদনা দলের লিখিত প্রস্তুতি রোডম্যাপ, তথ্যসূত্র এবং প্রশ্নোত্তর।'
                        : 'Syllabi analysis, evidence-backed exam roadmaps, and topic FAQs authored by verified educators.' ?>
                </li>
            </ul>

            <h2><?= $lang === 'bn' ? 'পরিচালনা অঞ্চল, ভাষা ও পেমেন্ট মাধ্যম' : 'Operations, Languages & Payment Methods' ?></h2>
            <ul>
                <li><strong><?= $lang === 'bn' ? 'কার্যক্রম এলাকা:' : 'Operating Region:' ?></strong> Bangladesh (<?= e($addr) ?>)</li>
                <li><strong><?= $lang === 'bn' ? 'সমর্থিত ভাষা:' : 'Languages:' ?></strong> Bangla (বাংলা — ডিফল্ট /) &amp; English (/en/)</li>
                <li><strong><?= $lang === 'bn' ? 'পেমেন্ট মাধ্যম:' : 'Accepted Payment Methods:' ?></strong> bKash, Nagad, Rocket<?= $shopOn ? ', Cash on Delivery (COD)' : '' ?> (BDT ৳)</li>
                <li><strong><?= $lang === 'bn' ? 'প্রতিষ্ঠাকাল:' : 'Founded:' ?></strong> <?= e(num_localize($founded)) ?></li>
                <li><strong><?= $lang === 'bn' ? 'যোগাযোগ:' : 'Customer Support:' ?></strong> <?= e($email) ?> · <?= e($phone) ?></li>
            </ul>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,210px),1fr));gap:16px;margin:24px 0;">
            <div style="padding:18px;border-radius:16px;background:var(--bg-alt);border:1px solid var(--border);">
                <div style="color:var(--primary);margin-bottom:8px;"><?= icon('folder', 'bx-icon-lg') ?></div>
                <strong style="font-size:1.4rem;font-family:var(--font-serif);" data-counter="<?= $folderCount ?>" data-counter-suffix="+"><?= e(num_localize($folderCount)) ?>+</strong>
                <div class="bx-muted"><?= $lang === 'bn' ? 'বিষয়ভিত্তিক ফোল্ডার' : 'Topic Folders' ?></div>
            </div>
            <div style="padding:18px;border-radius:16px;background:var(--bg-alt);border:1px solid var(--border);">
                <div style="color:var(--gold-text);margin-bottom:8px;"><?= icon('book', 'bx-icon-lg') ?></div>
                <strong style="font-size:1.4rem;font-family:var(--font-serif);" data-counter="<?= $itemCount ?>" data-counter-suffix="+"><?= e(num_localize($itemCount)) ?>+</strong>
                <div class="bx-muted"><?= $lang === 'bn' ? 'ডিজিটাল বই ও পিডিএফ' : 'Digital Books & PDFs' ?></div>
            </div>
            <div style="padding:18px;border-radius:16px;background:var(--bg-alt);border:1px solid var(--border);">
                <div style="color:var(--success);margin-bottom:8px;"><?= icon('shield', 'bx-icon-lg') ?></div>
                <strong style="font-size:1.4rem;font-family:var(--font-serif);" data-counter="100" data-counter-suffix="%">100%</strong>
                <div class="bx-muted"><?= $lang === 'bn' ? 'ওয়াটারমার্ক সুরক্ষিত রিডার' : 'Watermarked Browser Reader' ?></div>
            </div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding-top:14px;border-top:1px solid var(--border);">
            <a href="<?= e(url('/pdf')) ?>" class="bx-btn bx-btn-primary bx-btn-sm"><?= e(t('portal_pdf_cta')) ?></a>
            <a href="<?= e(url('/guides')) ?>" class="bx-btn bx-btn-outline bx-btn-sm"><?= e(t('nav_guides')) ?></a>
            <a href="<?= e(url('/faq')) ?>" class="bx-btn bx-btn-outline bx-btn-sm"><?= e(t('nav_faq')) ?></a>
            <a href="<?= e(url('/editorial-policy')) ?>" class="bx-btn bx-btn-ghost bx-btn-sm"><?= e(t('legal_editorial')) ?></a>
            <a href="<?= e(url('/contact')) ?>" class="bx-btn bx-btn-ghost bx-btn-sm"><?= e(t('nav_contact')) ?></a>
            <span class="bx-muted" style="font-size:0.82rem;margin-left:auto;"><?= $lang === 'bn' ? 'সর্বশেষ পর্যালোচনা:' : 'Last reviewed:' ?> <?= e(num_localize($reviewed)) ?></span>
        </div>
    </article>
</div>
