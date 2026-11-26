<?php
declare(strict_types=1);

$lang = current_lang();
$status = (string) ($_GET['status'] ?? '');
$where = '';
$params = [];
if (in_array($status, ['approved', 'pending', 'hidden'], true)) {
    $where = 'WHERE r.status = ?';
    $params[] = $status;
}

$reviews = db_all(
    "SELECT r.*, u.name AS user_name, u.email AS user_email, i.title_bn, i.title_en, i.slug
     FROM reviews r
     JOIN users u ON u.id = r.user_id
     JOIN items i ON i.id = r.item_id
     $where
     ORDER BY r.id DESC
     LIMIT 100",
    $params
);
$ret = $_SERVER['REQUEST_URI'] ?? '/admin/reviews';
?>

<section class="bx-card" style="margin-bottom:18px;padding:14px 18px;">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:space-between;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?= e(url('/admin/reviews')) ?>" class="bx-btn <?= $status === '' ? 'bx-btn-primary' : 'bx-btn-outline' ?>"><?= $lang === 'bn' ? 'সবগুলো' : 'All' ?></a>
            <a href="<?= e(url('/admin/reviews?status=approved')) ?>" class="bx-btn <?= $status === 'approved' ? 'bx-btn-primary' : 'bx-btn-outline' ?>"><?= $lang === 'bn' ? 'অনুমোদিত' : 'Approved' ?></a>
            <a href="<?= e(url('/admin/reviews?status=pending')) ?>" class="bx-btn <?= $status === 'pending' ? 'bx-btn-primary' : 'bx-btn-outline' ?>"><?= $lang === 'bn' ? 'অপেক্ষমাণ' : 'Pending' ?></a>
            <a href="<?= e(url('/admin/reviews?status=hidden')) ?>" class="bx-btn <?= $status === 'hidden' ? 'bx-btn-primary' : 'bx-btn-outline' ?>"><?= $lang === 'bn' ? 'লুকানো' : 'Hidden' ?></a>
        </div>
        <span class="bx-badge"><?= count($reviews) ?> <?= $lang === 'bn' ? 'টি রিভিউ' : 'review(s)' ?></span>
    </div>
</section>

<?php
$prodReviews = db_all(
    "SELECT pr.*, u.name AS user_name, u.email AS user_email, p.title_bn, p.title_en, p.slug
     FROM product_reviews pr
     JOIN users u ON u.id = pr.user_id
     JOIN products p ON p.id = pr.product_id
     {$where}
     ORDER BY pr.id DESC
     LIMIT 100",
    $params
);
?>
<section class="bx-card" style="margin-top:20px;">
    <h2 style="margin:0 0 14px;font-size:1.1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'প্রিন্টেড বই (শপ) রিভিউ ও রেটিং' : 'Physical Book (Shop) Reviews' ?> (<?= count($prodReviews) ?>)</h2>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'বই' : 'Product' ?></th>
                    <th><?= $lang === 'bn' ? 'ক্রেতা' : 'Verified Buyer' ?></th>
                    <th><?= $lang === 'bn' ? 'রেটিং ও মতামত' : 'Rating & Comment' ?></th>
                    <th><?= $lang === 'bn' ? 'অবস্থা' : 'Status' ?></th>
                    <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Actions' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$prodReviews): ?>
                    <tr class="bx-empty-row"><td colspan="5" style="color:var(--text-muted);padding:24px;"><?= $lang === 'bn' ? 'কোনো শপ রিভিউ পাওয়া যায়নি।' : 'No product reviews found.' ?></td></tr>
                <?php else: foreach ($prodReviews as $pr): $prid = (int) $pr['id']; $pst = (string) $pr['status']; ?>
                    <tr>
                        <td class="bx-td-primary">
                            <a href="<?= e(url('/shop/' . rawurlencode($pr['slug']))) ?>" target="_blank" rel="noopener" style="font-weight:700;color:var(--primary);">
                                <?= e($lang === 'bn' ? $pr['title_bn'] : $pr['title_en']) ?>
                            </a>
                            <div style="font-size:0.78rem;color:var(--text-muted);"><?= e(date('d M Y, H:i', strtotime((string) $pr['created_at']))) ?></div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'ক্রেতা' : 'Buyer') ?>">
                            <div><?= e($pr['user_name']) ?></div>
                            <small class="bx-ellipsis" style="color:var(--text-muted);"><?= e($pr['user_email']) ?></small>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'রিভিউ' : 'Review') ?>">
                            <?= render_stars_html((float) $pr['rating']) ?>
                            <div style="margin-top:4px;font-size:0.9rem;"><?= e((string) $pr['comment']) ?></div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'অবস্থা' : 'Status') ?>">
                            <span class="bx-status-pill <?= $pst === 'approved' ? 'st-approved' : ($pst === 'pending' ? 'st-pending' : 'st-rejected') ?>">
                                <?= e(ucfirst($pst)) ?>
                            </span>
                        </td>
                        <td class="bx-td-actions">
                            <?php if ($pst !== 'approved'): ?>
                                <form method="post" action="<?= e(url('/admin/reviews')) ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="shop_review_moderate">
                                    <input type="hidden" name="id" value="<?= $prid ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <input type="hidden" name="_return" value="<?= e($ret) ?>">
                                    <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= $lang === 'bn' ? 'অনুমোদন' : 'Approve' ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($pst !== 'hidden'): ?>
                                <form method="post" action="<?= e(url('/admin/reviews')) ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="shop_review_moderate">
                                    <input type="hidden" name="id" value="<?= $prid ?>">
                                    <input type="hidden" name="status" value="hidden">
                                    <input type="hidden" name="_return" value="<?= e($ret) ?>">
                                    <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'লুকান' : 'Hide' ?></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/admin/reviews')) ?>" data-confirm-msg="<?= e($lang === 'bn' ? 'এই রিভিউ মুছবেন?' : 'Delete product review?') ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="shop_review_delete">
                                <input type="hidden" name="id" value="<?= $prid ?>">
                                <input type="hidden" name="_return" value="<?= e($ret) ?>">
                                <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm" style="color:var(--rose-text);"><?= $lang === 'bn' ? 'মুছুন' : 'Delete' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="bx-card">
    <h2 style="margin:0 0 14px;font-size:1.1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'রিভিউ ও রেটিং মডারেশন' : 'Reviews & Ratings Moderation' ?></h2>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'বই' : 'PDF' ?></th>
                    <th><?= $lang === 'bn' ? 'পাঠক' : 'Reader' ?></th>
                    <th><?= $lang === 'bn' ? 'রেটিং ও মতামত' : 'Rating & Comment' ?></th>
                    <th><?= $lang === 'bn' ? 'অবস্থা' : 'Status' ?></th>
                    <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Actions' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$reviews): ?>
                    <tr class="bx-empty-row"><td colspan="5" style="color:var(--text-muted);padding:24px;"><?= $lang === 'bn' ? 'কোনো রিভিউ পাওয়া যায়নি।' : 'No reviews found.' ?></td></tr>
                <?php else: foreach ($reviews as $rv): ?>
                    <?php $rid = (int) $rv['id']; $st = (string) $rv['status']; ?>
                    <tr>
                        <td class="bx-td-primary">
                            <a href="<?= e(url('/book/' . rawurlencode($rv['slug']))) ?>" target="_blank" rel="noopener" style="font-weight:700;color:var(--primary);">
                                <?= e($lang === 'bn' ? $rv['title_bn'] : $rv['title_en']) ?>
                            </a>
                            <div style="font-size:0.78rem;color:var(--text-muted);"><?= e(date('d M Y, H:i', strtotime((string) $rv['created_at']))) ?></div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'পাঠক' : 'Reader') ?>">
                            <div>
                                <div><?= e($rv['user_name']) ?></div>
                                <small class="bx-ellipsis" style="color:var(--text-muted);"><?= e($rv['user_email']) ?></small>
                            </div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'রিভিউ' : 'Review') ?>">
                            <div>
                                <?= render_stars_html((float) $rv['rating']) ?>
                                <div style="margin-top:4px;font-size:0.9rem;"><?= e($rv['comment']) ?></div>
                            </div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'অবস্থা' : 'Status') ?>">
                            <span class="bx-status-pill <?= $st === 'approved' ? 'st-approved' : ($st === 'pending' ? 'st-pending' : 'st-rejected') ?>">
                                <?= e(ucfirst($st)) ?>
                            </span>
                        </td>
                        <td class="bx-td-actions">
                            <?php if ($st !== 'approved'): ?>
                                <form method="post" action="<?= e(url('/admin/reviews')) ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="review_moderate">
                                    <input type="hidden" name="id" value="<?= $rid ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <input type="hidden" name="_return" value="<?= e($ret) ?>">
                                    <button type="submit" class="bx-btn bx-btn-primary" style="padding:8px 12px;font-size:0.84rem;"><?= $lang === 'bn' ? 'অনুমোদন' : 'Approve' ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($st !== 'hidden'): ?>
                                <form method="post" action="<?= e(url('/admin/reviews')) ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="review_moderate">
                                    <input type="hidden" name="id" value="<?= $rid ?>">
                                    <input type="hidden" name="status" value="hidden">
                                    <input type="hidden" name="_return" value="<?= e($ret) ?>">
                                    <button type="submit" class="bx-btn bx-btn-outline" style="padding:8px 12px;font-size:0.84rem;"><?= $lang === 'bn' ? 'লুকান' : 'Hide' ?></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/admin/reviews')) ?>" data-confirm-msg="<?= e($lang === 'bn' ? 'এই রিভিউটি স্থায়ীভাবে মুছে ফেলবেন?' : 'Delete this review permanently?') ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="review_delete">
                                <input type="hidden" name="id" value="<?= $rid ?>">
                                <input type="hidden" name="_return" value="<?= e($ret) ?>">
                                <button type="submit" class="bx-btn bx-btn-ghost" style="padding:8px 12px;font-size:0.84rem;color:var(--rose-text);"><?= $lang === 'bn' ? 'মুছুন' : 'Delete' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php
$prodReviews = db_all(
    "SELECT pr.*, u.name AS user_name, u.email AS user_email, p.title_bn, p.title_en, p.slug
     FROM product_reviews pr
     JOIN users u ON u.id = pr.user_id
     JOIN products p ON p.id = pr.product_id
     {$where}
     ORDER BY pr.id DESC
     LIMIT 100",
    $params
);
?>
<section class="bx-card" style="margin-top:20px;">
    <h2 style="margin:0 0 14px;font-size:1.1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'প্রিন্টেড বই (শপ) রিভিউ ও রেটিং' : 'Physical Book (Shop) Reviews' ?> (<?= count($prodReviews) ?>)</h2>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'বই' : 'Product' ?></th>
                    <th><?= $lang === 'bn' ? 'ক্রেতা' : 'Verified Buyer' ?></th>
                    <th><?= $lang === 'bn' ? 'রেটিং ও মতামত' : 'Rating & Comment' ?></th>
                    <th><?= $lang === 'bn' ? 'অবস্থা' : 'Status' ?></th>
                    <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Actions' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$prodReviews): ?>
                    <tr class="bx-empty-row"><td colspan="5" style="color:var(--text-muted);padding:24px;"><?= $lang === 'bn' ? 'কোনো শপ রিভিউ পাওয়া যায়নি।' : 'No product reviews found.' ?></td></tr>
                <?php else: foreach ($prodReviews as $pr): $prid = (int) $pr['id']; $pst = (string) $pr['status']; ?>
                    <tr>
                        <td class="bx-td-primary">
                            <a href="<?= e(url('/shop/' . rawurlencode($pr['slug']))) ?>" target="_blank" rel="noopener" style="font-weight:700;color:var(--primary);">
                                <?= e($lang === 'bn' ? $pr['title_bn'] : $pr['title_en']) ?>
                            </a>
                            <div style="font-size:0.78rem;color:var(--text-muted);"><?= e(date('d M Y, H:i', strtotime((string) $pr['created_at']))) ?></div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'ক্রেতা' : 'Buyer') ?>">
                            <div><?= e($pr['user_name']) ?></div>
                            <small class="bx-ellipsis" style="color:var(--text-muted);"><?= e($pr['user_email']) ?></small>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'রিভিউ' : 'Review') ?>">
                            <?= render_stars_html((float) $pr['rating']) ?>
                            <div style="margin-top:4px;font-size:0.9rem;"><?= e((string) $pr['comment']) ?></div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'অবস্থা' : 'Status') ?>">
                            <span class="bx-status-pill <?= $pst === 'approved' ? 'st-approved' : ($pst === 'pending' ? 'st-pending' : 'st-rejected') ?>">
                                <?= e(ucfirst($pst)) ?>
                            </span>
                        </td>
                        <td class="bx-td-actions">
                            <?php if ($pst !== 'approved'): ?>
                                <form method="post" action="<?= e(url('/admin/reviews')) ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="shop_review_moderate">
                                    <input type="hidden" name="id" value="<?= $prid ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <input type="hidden" name="_return" value="<?= e($ret) ?>">
                                    <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= $lang === 'bn' ? 'অনুমোদন' : 'Approve' ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($pst !== 'hidden'): ?>
                                <form method="post" action="<?= e(url('/admin/reviews')) ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="shop_review_moderate">
                                    <input type="hidden" name="id" value="<?= $prid ?>">
                                    <input type="hidden" name="status" value="hidden">
                                    <input type="hidden" name="_return" value="<?= e($ret) ?>">
                                    <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'লুকান' : 'Hide' ?></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/admin/reviews')) ?>" data-confirm-msg="<?= e($lang === 'bn' ? 'এই রিভিউ মুছবেন?' : 'Delete product review?') ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="shop_review_delete">
                                <input type="hidden" name="id" value="<?= $prid ?>">
                                <input type="hidden" name="_return" value="<?= e($ret) ?>">
                                <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm" style="color:var(--rose-text);"><?= $lang === 'bn' ? 'মুছুন' : 'Delete' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
