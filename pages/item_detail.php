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

$cover = !empty($item['cover']) ? asset($item['cover']) : asset('uploads/covers/cover-bangla.svg');
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
        <div class="bx-detail-stage">
            <div class="bx-detail-book-3d">
                <img src="<?= e($cover) ?>" alt="<?= e(bi($item, 'title')) ?>" width="400" height="600" decoding="async">
                <button type="button"
                        class="bx-wish-btn bx-detail-wish<?= $isWished ? ' active' : '' ?>"
                        data-wish-btn
                        data-item-id="<?= $itemId ?>"
                        aria-pressed="<?= $isWished ? 'true' : 'false' ?>"
                        aria-label="<?= e($lang === 'bn' ? 'পছন্দের তালিকায় রাখুন' : 'Toggle wishlist') ?>"><?= icon('heart') ?></button>
            </div>
        </div>

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
                        <?= e(t('author_label')) ?>: <?= e(bi($item, 'author')) ?>
                    </p>
                <?php endif; ?>
            </div>

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
                    <a href="<?= e($readUrl) ?>" class="bx-btn bx-btn-primary">
                        <?= icon('book') ?> <?= e(t('read_now_btn')) ?>
                    </a>
                <?php elseif ($pendingOrder): ?>
                    <span class="bx-badge bx-badge-gold" style="padding:10px 16px;font-size:0.9rem;">
                        <?= current_lang() === 'bn' ? 'পেমেন্ট যাচাই চলছে' : 'Verification Pending' ?> (#<?= e($pendingOrder['order_code']) ?>)
                    </span>
                <?php else: ?>
                    <a href="<?= e($buyUrl) ?>" class="bx-btn bx-btn-accent">
                        <?= icon('lock') ?> <?= e(t('buy_now_btn')) ?> — <?= e(format_bdt((float) $item['price'])) ?>
                    </a>
                <?php endif; ?>

                <?php if ((int) $item['sample_pages'] > 0 && !$isFree && !$isOwned): ?>
                    <a href="<?= e($sampleUrl) ?>" class="bx-btn bx-btn-outline">
                        <?= icon('eye') ?> <?= e(t('read_sample_btn')) ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php
            $shareAbsUrl = absolute_url_for_lang('/book/' . rawurlencode($item['slug']), $lang);
            $shareTitle  = bi($item, 'title');
            $waShareUrl  = 'https://wa.me/?text=' . rawurlencode($shareTitle . ' — ' . $shareAbsUrl);
            $fbShareUrl  = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($shareAbsUrl);
            ?>
            <div class="bx-share-bar" aria-label="<?= e($lang === 'bn' ? 'শেয়ার করুন' : 'Share this book') ?>">
                <span class="bx-share-label"><?= $lang === 'bn' ? 'শেয়ার করুন:' : 'Share:' ?></span>
                <a href="<?= e($waShareUrl) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-ghost bx-btn-sm">
                    WhatsApp
                </a>
                <a href="<?= e($fbShareUrl) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-ghost bx-btn-sm">
                    Facebook
                </a>
                <button type="button" class="bx-btn bx-btn-ghost bx-btn-sm"
                        data-copy-link="<?= e($shareAbsUrl) ?>"
                        data-copied-label="<?= e($lang === 'bn' ? 'লিংক কপি হয়েছে!' : 'Link copied!') ?>">
                    <?= $lang === 'bn' ? 'লিংক কপি' : 'Copy Link' ?>
                </button>
            </div>
        </div>
    </div>

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
</div>
