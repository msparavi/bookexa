<?php
declare(strict_types=1);
/** @var array $item */
$user = current_user();
$itemId = (int) $item['id'];
$isFree = (bool) $item['is_free'] || (float) $item['price'] <= 0;
$isOwned = $user ? user_owns_item((int) $user['id'], $itemId) : false;
$pendingOrder = $user ? user_pending_order((int) $user['id'], $itemId) : null;
$isWished = $user ? user_has_wishlisted((int) $user['id'], $itemId) : false;
$canReview = $user ? user_can_review_item((int) $user['id'], $itemId) : false;
$userReview = $user ? db_one('SELECT * FROM reviews WHERE user_id = ? AND item_id = ? LIMIT 1', [(int) $user['id'], $itemId]) : null;
$ratingSummary = item_rating_summary($itemId);
$reviewsList = db_all(
    "SELECT r.*, u.name AS user_name
     FROM reviews r
     JOIN users u ON u.id = r.user_id
     WHERE r.item_id = ? AND r.status = 'approved'
     ORDER BY r.id DESC
     LIMIT 30",
    [$itemId]
);
$lang = current_lang();

$coverRel = !empty($item['cover']) ? (string) $item['cover'] : 'uploads/covers/cover-bangla.svg';
$breadcrumbs = $item['folder_id'] ? folder_breadcrumbs((int) $item['folder_id']) : [];

$related = $item['folder_id']
    ? db_all('SELECT * FROM items WHERE folder_id = ? AND id != ? AND is_active = 1 ORDER BY id DESC LIMIT 4', [(int) $item['folder_id'], $itemId])
    : [];

$readUrl   = url('/reader/' . rawurlencode($item['slug']));
$sampleUrl = url('/reader/' . rawurlencode($item['slug']) . '?sample=1');
$buyUrl    = url('/checkout/' . rawurlencode($item['slug']));

// Track item view popularity & per-user recently viewed (guests tracked via localStorage)
try {
    db_exec('UPDATE items SET view_count = view_count + 1 WHERE id = ?', [$itemId]);
    if ($user) {
        db_exec(
            'INSERT INTO recently_viewed (user_id, item_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE viewed_at = NOW()',
            [(int) $user['id'], $itemId]
        );
    }
} catch (Throwable $e) {
}
?>
<div class="container" style="padding-top:20px;" data-recent-item="<?= $itemId ?>">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <a href="<?= e(url('/pdf')) ?>"><?= e(t('nav_pdfs')) ?></a>
        <?php $accum = []; foreach ($breadcrumbs as $crumb): $accum[] = rawurlencode($crumb['slug']); ?>
            <?= icon('chevron-right') ?>
            <a href="<?= e(url('/pdf/' . implode('/', $accum))) ?>"><?= e(bi($crumb, 'name')) ?></a>
        <?php endforeach; ?>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(bi($item, 'title')) ?></span>
    </nav>

    <div class="bx-detail-grid">
        <figure class="bx-detail-stage" style="margin:0;">
            <div class="bx-detail-book-3d">
                <?= render_responsive_picture($coverRel, bi($item, 'title'), [
                    'width'  => 400,
                    'height' => 600,
                    'sizes'  => '(max-width: 768px) 72vw, 340px',
                    'is_lcp' => true,
                ]) ?>
                <button type="button"
                        class="bx-wish-btn bx-detail-wish<?= $isWished ? ' active' : '' ?>"
                        data-wish-btn
                        data-item-id="<?= $itemId ?>"
                        aria-pressed="<?= $isWished ? 'true' : 'false' ?>"
                        aria-label="<?= e($lang === 'bn' ? 'পছন্দের তালিকায় রাখুন' : 'Toggle wishlist') ?>"><?= icon('heart') ?></button>
            </div>
            <figcaption class="bx-muted" style="font-size:0.78rem;text-align:center;margin-top:8px;"><?= e(bi($item, 'title')) ?></figcaption>
        </figure>

        <div class="bx-card bx-detail-info-card">
            <div>
                <span class="bx-badge <?= $isOwned ? 'bx-badge-gold' : ($isFree ? 'bx-badge-success' : 'bx-badge-accent') ?>" style="margin-bottom:10px;">
                    <?= e($isOwned ? (current_lang() === 'bn' ? 'লাইব্রেরিতে যুক্ত' : 'Unlocked') : ($isFree ? t('free_badge') : format_bdt((float) $item['price']))) ?>
                </span>
                <h1 class="bx-display" style="font-size:clamp(1.4rem,2.6vw,2.05rem);margin-bottom:6px;">
                    <?= e(bi($item, 'title')) ?>
                </h1>
                <?php if (bi($item, 'author') !== ''): ?>
                    <p class="bx-muted" style="margin:0;font-weight:600;">
                        <?= e(t('author_label')) ?>:
                        <?php $aSlugLink = slugify((string) ($item['author_en'] ?: $item['author_bn'])); ?>
                        <a href="<?= e(url('/authors/' . rawurlencode($aSlugLink))) ?>" style="color:var(--primary);text-decoration:underline;"><?= e(bi($item, 'author')) ?></a>
                    </p>
                <?php endif; ?>
                <?php
                $pubFmt = num_localize(date('d M Y', strtotime((string) ($item['publish_at'] ?: $item['created_at'] ?: 'now')) ?: time()));
                $updFmt = num_localize(date('d M Y', strtotime((string) ($item['updated_at'] ?: $item['created_at'] ?: 'now')) ?: time()));
                ?>
                <p class="bx-muted" style="margin:6px 0 0;font-size:0.8rem;">
                    <span><?= $lang === 'bn' ? 'প্রকাশ:' : 'Published:' ?> <?= e($pubFmt) ?></span>
                    ·
                    <span><strong><?= $lang === 'bn' ? 'হালনাগাদ:' : 'Updated:' ?></strong> <?= e($updFmt) ?></span>
                </p>
            </div>

            <?php $bookAns = aeo_short_answer($item, bi($item, 'description')); ?>
            <?php if ($bookAns !== ''): ?>
                <div class="bx-answer-box">
                    <span class="bx-answer-kicker"><?= $lang === 'bn' ? 'সংক্ষিপ্ত পরিচিতি' : 'Quick Summary' ?></span>
                    <p><?= e($bookAns) ?></p>
                </div>
            <?php endif; ?>

            <div class="bx-meta-pills">
                <span class="bx-meta-pill">
                    <?= icon('book') ?>
                    <?php if ($isFree): ?>
                        <?= e(t('free_badge')) ?>
                    <?php else: ?>
                        <?= e(format_bdt((float) $item['price'])) ?>
                        <?php if (!empty($item['original_price']) && (float) $item['original_price'] > (float) $item['price']): ?>
                            <span class="bx-price-old"><?= e(format_bdt((float) $item['original_price'])) ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </span>
                <?php if (!empty($item['total_pages'])): ?>
                    <span class="bx-meta-pill"><?= icon('layers') ?> <?= e(num_localize((int) $item['total_pages'])) ?> <?= e(t('pages_label')) ?></span>
                <?php endif; ?>
                <?php if (!empty($item['isbn'])): ?>
                    <span class="bx-meta-pill">ISBN: <?= e((string) $item['isbn']) ?></span>
                <?php endif; ?>
                <?php if (!empty($item['publisher'])): ?>
                    <span class="bx-meta-pill"><?= e((string) $item['publisher']) ?></span>
                <?php endif; ?>
                <?php if (!empty($item['edition'])): ?>
                    <span class="bx-meta-pill"><?= e((string) $item['edition']) ?></span>
                <?php endif; ?>
                <?php if (!empty($item['book_language'])): ?>
                    <span class="bx-meta-pill"><?= e((string) $item['book_language']) ?></span>
                <?php endif; ?>
                <?php if ((int) $item['sample_pages'] > 0): ?>
                    <span class="bx-meta-pill"><?= icon('eye') ?> <?= e(t('sample_label')) ?>: <?= e(t('first_n_pages', num_localize((int) $item['sample_pages']))) ?></span>
                <?php endif; ?>
                <?php if ($ratingSummary['count'] > 0): ?>
                    <span class="bx-meta-pill bx-meta-rating">★ <?= e(num_localize((string) $ratingSummary['avg'])) ?> / <?= e(num_localize('5')) ?> (<?= e(num_localize((string) $ratingSummary['count'])) ?>)</span>
                <?php endif; ?>
            </div>

            <p style="white-space:pre-line;margin:0 0 18px;"><?= e(bi($item, 'description')) ?></p>

            <div class="bx-security-box">
                <?= icon('shield', 'bx-icon-lg') ?>
                <span><?= e(t('security_note')) ?></span>
            </div>

            <div class="bx-detail-actions">
                <?php if ($isFree || $isOwned): ?>
                    <a href="<?= e($readUrl) ?>" class="bx-btn bx-btn-primary bx-cta-primary">
                        <?= icon('book') ?> <?= e(t('read_now_btn')) ?>
                    </a>
                <?php elseif ($pendingOrder): ?>
                    <span class="bx-badge bx-badge-gold bx-cta-pending" style="padding:10px 14px;font-size:0.88rem;">
                        <?= current_lang() === 'bn' ? 'পেমেন্ট যাচাই চলছে' : 'Verification Pending' ?> (#<?= e($pendingOrder['order_code']) ?>)
                    </span>
                <?php else: ?>
                    <?php $priceText = format_bdt((float) $item['price']); ?>
                    <a href="<?= e($buyUrl) ?>" class="bx-btn bx-btn-accent bx-cta-primary">
                        <?= icon('lock') ?>
                        <span class="bx-cta-long"><?= e(t('buy_now_btn')) ?> — <?= e($priceText) ?></span>
                        <span class="bx-cta-short"><?= e($lang === 'bn' ? 'কিনুন' : 'Buy') ?> <?= e($priceText) ?></span>
                    </a>
                <?php endif; ?>

                <?php if ((int) $item['sample_pages'] > 0 && !$isFree && !$isOwned): ?>
                    <a href="<?= e($sampleUrl) ?>" class="bx-btn bx-btn-outline bx-cta-sample">
                        <?= icon('eye') ?>
                        <span class="bx-cta-long"><?= e(t('read_sample_btn')) ?></span>
                        <span class="bx-cta-short"><?= e($lang === 'bn' ? 'ফ্রি স্যাম্পল' : 'Free sample') ?></span>
                    </a>
                <?php endif; ?>
            </div>

            <?= render_social_share_bar(
                absolute_url_for_lang('/book/' . rawurlencode((string) $item['slug']), $lang),
                bi($item, 'title'),
                $lang === 'bn' ? 'শেয়ার করুন:' : 'Share:'
            ) ?>
        </div>
    </div>

    <!-- Structured Book Description: Learnings, Audience, PDF Outline TOC, Sample Thumbnails & FAQs (Zero protected text exposed) -->
    <?php
    $whatLearnRaw = trim($lang === 'bn' ? (string) ($item['what_you_learn_bn'] ?: $item['what_you_learn_en'] ?? '') : (string) ($item['what_you_learn_en'] ?: $item['what_you_learn_bn'] ?? ''));
    $learnItems = $whatLearnRaw !== '' ? array_values(array_filter(array_map('trim', preg_split('/\R/u', $whatLearnRaw) ?: []))) : [];
    $whoForBook = trim($lang === 'bn' ? (string) ($item['who_for_bn'] ?: $item['who_for_en'] ?? '') : (string) ($item['who_for_en'] ?: $item['who_for_bn'] ?? ''));
    $tocChapters = !empty($tocChapters) && is_array($tocChapters) ? $tocChapters : [];
    $bookFaqs = !empty($item['faqs_json']) ? content_parse_faqs_json($item['faqs_json']) : [];
    $bookTags = !empty($item['tags']) ? content_parse_tags($item['tags']) : [];
    $sampleThumbPages = !empty($sampleThumbPages) ? $sampleThumbPages : [];
    ?>
    <?php if ($learnItems || $whoForBook !== '' || $tocChapters || $sampleThumbPages || $bookFaqs || $bookTags): ?>
        <section class="bx-section">
            <div class="bx-card" style="padding:22px;display:flex;flex-direction:column;gap:18px;">
                <?php if ($learnItems): ?>
                    <div>
                        <h2 class="bx-h2" style="font-size:1.2rem;margin:0 0 10px;"><?= $lang === 'bn' ? 'এই বই থেকে কী শিখবেন?' : 'What You Will Learn' ?></h2>
                        <ul style="margin:0;padding-left:20px;line-height:1.7;">
                            <?php foreach ($learnItems as $li): ?>
                                <li><?= e($li) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($whoForBook !== ''): ?>
                    <div style="padding:12px 16px;border-radius:var(--radius-sm);background:var(--bg-alt);border-left:4px solid var(--teal);">
                        <strong style="display:block;margin-bottom:4px;"><?= $lang === 'bn' ? 'কাদের জন্য এই বই?' : 'Who Is This Book For?' ?></strong>
                        <p class="bx-muted" style="margin:0;line-height:1.65;"><?= e($whoForBook) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($tocChapters): ?>
                    <div>
                        <h2 class="bx-h2" style="font-size:1.2rem;margin:0 0 10px;"><?= $lang === 'bn' ? 'বইয়ের সূচিপত্র (অধ্যায় তালিকা)' : 'Table of Contents' ?></h2>
                        <ul style="margin:0;padding-left:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr));gap:6px 18px;">
                            <?php foreach (array_slice($tocChapters, 0, 24) as $ch): ?>
                                <li>
                                    <?= e((string) ($ch['title'] ?? $ch['name'] ?? '')) ?>
                                    <?php if (!empty($ch['page'])): ?>
                                        <small class="bx-muted">(<?= $lang === 'bn' ? 'পৃষ্ঠা ' : 'p.' ?><?= e(num_localize((int) $ch['page'])) ?>)</small>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($sampleThumbPages): ?>
                    <div>
                        <h2 class="bx-h2" style="font-size:1.2rem;margin:0 0 10px;"><?= $lang === 'bn' ? 'নমুনা পৃষ্ঠার প্রিভিউ' : 'Sample Page Previews' ?></h2>
                        <div class="bx-sample-thumbs">
                            <?php foreach ($sampleThumbPages as $spNum): ?>
                                <a href="<?= e($sampleUrl) ?>" class="bx-sample-thumb">
                                    <img src="<?= e(url('/sample-thumb/' . rawurlencode((string) $item['slug']) . '/' . (int) $spNum)) ?>"
                                         alt="<?= e(bi($item, 'title') . ' Sample Page ' . $spNum) ?>" width="340" height="450" loading="lazy">
                                    <span style="display:block;padding:6px;"><?= $lang === 'bn' ? 'পৃষ্ঠা ' . num_localize((int) $spNum) : 'Page ' . (int) $spNum ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($bookTags): ?>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <span class="bx-muted" style="font-size:0.84rem;font-weight:600;"><?= $lang === 'bn' ? 'ট্যাগ:' : 'Tags:' ?></span>
                        <?php foreach ($bookTags as $bt): ?>
                            <a href="<?= e(url('/search?q=' . rawurlencode($bt))) ?>" class="bx-chip" style="text-decoration:none;">#<?= e($bt) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($bookFaqs): ?>
                    <div>
                        <h2 class="bx-h2" style="font-size:1.2rem;margin:0 0 10px;"><?= $lang === 'bn' ? 'বই সম্পর্কিত প্রশ্নোত্তর (FAQ)' : 'Book FAQs' ?></h2>
                        <?php foreach ($bookFaqs as $fq): ?>
                            <?php $qt = $lang === 'bn' ? ($fq['q_bn'] ?: $fq['q_en']) : ($fq['q_en'] ?: $fq['q_bn']); ?>
                            <?php $at = $lang === 'bn' ? ($fq['a_bn'] ?: $fq['a_en']) : ($fq['a_en'] ?: $fq['a_bn']); ?>
                            <?php $fqUrl = trim((string) ($fq['url'] ?? '')); ?>
                            <?php if ($qt !== ''): ?>
                                <details class="bx-faq-item" open>
                                    <summary><?= e($qt) ?></summary>
                                    <p>
                                        <?= e($at) ?>
                                        <?php if ($fqUrl !== ''): ?>
                                            <a href="<?= e(str_starts_with($fqUrl, 'http') ? $fqUrl : url($fqUrl)) ?>" style="display:inline-block;margin-left:6px;font-weight:700;color:var(--primary);"><?= $lang === 'bn' ? 'বিস্তারিত →' : 'Learn more →' ?></a>
                                        <?php endif; ?>
                                    </p>
                                </details>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="bx-section" id="reviews">
        <div class="bx-reviews-grid">
            <div class="bx-card">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
                    <div>
                        <h2 class="bx-h2"><?= e(t('reviews_title')) ?></h2>
                        <?php if ($ratingSummary['count'] > 0): ?>
                            <div style="display:flex;align-items:center;gap:8px;margin-top:4px;flex-wrap:wrap;">
                                <?= render_stars_html($ratingSummary['avg']) ?>
                                <strong><?= e(num_localize((string) $ratingSummary['avg'])) ?> / <?= e(num_localize('5')) ?></strong>
                                <span class="bx-muted">(<?= e(num_localize((string) $ratingSummary['count'])) ?> <?= $lang === 'bn' ? 'টি রিভিউ' : 'reviews' ?>)</span>
                            </div>
                        <?php else: ?>
                            <p class="bx-muted" style="margin:4px 0 0;"><?= $lang === 'bn' ? 'এখনও কোনো রিভিউ নেই।' : 'No reviews yet.' ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$user): ?>
                    <div class="bx-review-notice">
                        <?= $lang === 'bn' ? 'রিভিউ লিখতে ' : 'Please ' ?>
                        <a href="<?= e(url('/login?next=' . rawurlencode('/book/' . rawurlencode($item['slug'])))) ?>" style="color:var(--primary);font-weight:700;text-decoration:underline;"><?= e(t('nav_login')) ?></a>
                        <?= $lang === 'bn' ? ' করুন (কেবল ক্রেতা বা পাঠক রিভিউ দিতে পারবেন)।' : ' to leave a review (for readers & owners of this PDF).' ?>
                    </div>
                <?php elseif (!$canReview): ?>
                    <div class="bx-review-notice">
                        <?= $lang === 'bn' ? 'কেবল এই বইটি কিনেছেন বা রিডারে পড়েছেন এমন পাঠক রিভিউ দিতে পারবেন।' : 'Only users who own or have read this PDF can submit a review.' ?>
                    </div>
                <?php elseif ($userReview): ?>
                    <div class="bx-review-notice">
                        <?= $lang === 'bn' ? 'আপনার রিভিউ জমা হয়েছে (' : 'Your review is recorded (' ?>
                        <strong><?= e(ucfirst((string) $userReview['status'])) ?></strong>).
                    </div>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/book/' . rawurlencode($item['slug']) . '/review')) ?>" class="bx-review-form" data-loading-form>
                        <?= csrf_field() ?>
                        <div class="bx-field">
                            <label><?= $lang === 'bn' ? 'রেটিং (১–৫ স্টার) *' : 'Your Rating (1–5 Stars) *' ?></label>
                            <div class="bx-rating-picker" role="radiogroup" aria-label="<?= $lang === 'bn' ? 'স্টার রেটিং' : 'Star rating' ?>">
                                <?php for ($st = 1; $st <= 5; $st++): ?>
                                    <label class="bx-star-opt">
                                        <input type="radio" name="rating" value="<?= $st ?>" <?= $st === 5 ? 'checked' : '' ?> required>
                                        <span><?= e(num_localize((string) $st)) ?> ★</span>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="bx-field">
                            <label for="rev-comment"><?= $lang === 'bn' ? 'আপনার সংক্ষিপ্ত মতামত (বাংলা বা ইংরেজি) *' : 'Short Review (Bangla or English) *' ?></label>
                            <textarea class="bx-input" id="rev-comment" name="comment" rows="3" minlength="3" maxlength="600" required placeholder="<?= $lang === 'bn' ? 'বইটি কেমন লাগল লিখুন...' : 'Share what you found helpful...' ?>"></textarea>
                        </div>
                        <button type="submit" class="bx-btn bx-btn-primary" data-loading-label="<?= e($lang === 'bn' ? 'জমা হচ্ছে...' : 'Submitting...') ?>">
                            <?= $lang === 'bn' ? 'রিভিউ জমা দিন' : 'Submit Review' ?>
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($reviewsList)): ?>
                    <div class="bx-review-list">
                        <?php foreach ($reviewsList as $rev): ?>
                            <article class="bx-review-item">
                                <div class="bx-review-head">
                                    <strong><?= e($rev['user_name']) ?></strong>
                                    <?= render_stars_html((float) $rev['rating']) ?>
                                    <small class="bx-muted"><?= e(date('d M Y', strtotime((string) $rev['created_at']))) ?></small>
                                </div>
                                <p style="margin:6px 0 0;white-space:pre-line;"><?= e($rev['comment']) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if (!empty($related)): ?>
        <section class="bx-section">
            <h2 class="bx-h2" style="margin-bottom:18px;"><?= e(t('related_pdfs')) ?></h2>
            <div class="bx-book-grid">
                <?php foreach ($related as $rel): ?>
                    <?= render_book_card($rel) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($relatedByTag)): ?>
        <section class="bx-section">
            <h2 class="bx-h2" style="margin-bottom:18px;"><?= $lang === 'bn' ? 'একই বিষয়ের অন্যান্য বই (ট্যাগ অনুযায়ী)' : 'Related Books by Topic Tag' ?></h2>
            <div class="bx-book-grid">
                <?php foreach ($relatedByTag as $relT): ?>
                    <?= render_book_card($relT) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
