<?php
declare(strict_types=1);
/** @var array $groupedFaqs, array $topicLabels */
$topicLabels ??= aeo_faq_topics();
if (!isset($groupedFaqs) || !is_array($groupedFaqs)) {
    $groupedFaqs = [];
    foreach (($faqRows ?? []) as $fqRow) {
        $tk = trim((string) ($fqRow['topic'] ?: 'general'));
        $groupedFaqs[$tk][] = $fqRow;
    }
}
$lang = current_lang();
$reviewed = site_setting('legal_last_reviewed', date('Y-m-d'));
?>
<div class="container" style="padding-top:20px;padding-bottom:46px;max-width:920px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(t('nav_faq')) ?></span>
    </nav>

    <div class="bx-page-hero is-faq" style="margin:16px 0 22px;">
        <div class="bx-page-hero-copy">
            <span class="bx-badge bx-badge-teal" style="margin-bottom:8px;"><?= icon('sparkles') ?> <?= $lang === 'bn' ? 'সাহায্য কেন্দ্র' : 'Knowledge Base' ?></span>
            <h1 class="bx-display" style="font-size:clamp(1.5rem,3vw,2.1rem);margin:0 0 10px;">
                <?= $lang === 'bn' ? 'সচরাচর জিজ্ঞাসিত প্রশ্নোত্তর (FAQ)' : 'Frequently Asked Questions (FAQ)' ?>
            </h1>
            <div class="bx-muted" style="font-size:0.88rem;">
                <?= $lang === 'bn' ? 'সর্বশেষ পর্যালোচনা:' : 'Last reviewed:' ?> <strong><?= e(num_localize($reviewed)) ?></strong>
            </div>
        </div>
        <div class="bx-page-hero-art">
            <?= illustration_svg('faq') ?>
        </div>
    </div>

    <div class="bx-answer-box" style="margin-bottom:22px;">
        <span class="bx-answer-kicker"><?= $lang === 'bn' ? 'সংক্ষিপ্ত উত্তর' : 'Quick Summary' ?></span>
        <p>
            <?= $lang === 'bn'
                ? 'Bookexa-তে বিকাশ, নগদ ও রকেট বা ক্যাশ অন ডেলিভারির মাধ্যমে সহজে বই সংগ্রহ করা যায়। পেমেন্ট যাচাই শেষে অ্যাকাউন্টের ২টি বিশ্বস্ত ডিভাইসে নিরাপদ ওয়াটারমার্ক ব্রাউজার রিডারে সাথে সাথে পড়া যায় এবং ভুল বা ডাবল পেমেন্টে ৪৮ ঘণ্টার মধ্যে রিফান্ড সহায়তা পাওয়া যায়।'
                : 'Readers on Bookexa pay safely via bKash, Nagad, Rocket, or Cash on Delivery across Bangladesh. Verified PDFs unlock directly in My Library for watermarked reading across up to two trusted devices in any mobile or desktop browser, backed by transparent 48-hour duplicate-payment refund support.' ?>
        </p>
    </div>

    <!-- Jump links by topic -->
    <div class="bx-shop-cat-pills" style="margin-bottom:24px;">
        <?php foreach ($groupedFaqs as $tKey => $rows): ?>
            <a href="#faq-topic-<?= e($tKey) ?>" class="bx-cat-pill">
                <?= e($topicLabels[$tKey][$lang] ?? ucfirst(str_replace('_', ' ', $tKey))) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php foreach ($groupedFaqs as $tKey => $rows): ?>
        <section id="faq-topic-<?= e($tKey) ?>" style="margin-bottom:28px;">
            <h2 class="bx-h2" style="margin:0 0 12px;">
                <?= e($topicLabels[$tKey][$lang] ?? ucfirst(str_replace('_', ' ', $tKey))) ?>
                <a class="bx-heading-anchor" href="#faq-topic-<?= e($tKey) ?>" aria-label="Permalink">#</a>
            </h2>
            <?php foreach ($rows as $fq): ?>
                <?php
                $qTxt = $lang === 'bn' ? ($fq['question_bn'] ?: $fq['question_en']) : ($fq['question_en'] ?: $fq['question_bn']);
                $aTxt = $lang === 'bn' ? ($fq['answer_bn'] ?: $fq['answer_en']) : ($fq['answer_en'] ?: $fq['answer_bn']);
                $lUrl = trim((string) ($fq['link_url'] ?? ''));
                ?>
                <details class="bx-faq-item" open>
                    <summary><?= e($qTxt) ?></summary>
                    <p><?= e($aTxt) ?></p>
                    <?php if ($lUrl !== ''): ?>
                        <p style="margin-top:8px;">
                            <a href="<?= e(url($lUrl)) ?>" style="font-weight:700;color:var(--primary);">
                                <?= $lang === 'bn' ? 'সম্পর্কিত পাতা দেখুন →' : 'Learn more →' ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </details>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</div>
