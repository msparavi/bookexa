<?php
declare(strict_types=1);
/** @var array $notifications */
$lang = current_lang();
?>
<div class="container" style="padding-top:24px;max-width:860px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(t('nav_notifications')) ?></span>
    </nav>

    <div class="bx-section-head" style="margin-top:20px;">
        <div>
            <h1 class="bx-h2"><?= e(t('nav_notifications')) ?></h1>
            <p><?= $lang === 'bn' ? 'অর্ডার অনুমোদন, বাতিল ও পছন্দের ফোল্ডারের নতুন বইয়ের আপডেট।' : 'Updates for your orders and newly published PDFs in folders you opened.' ?></p>
        </div>
        <?php if (!empty($notifications)): ?>
            <form method="post" action="<?= e(url('/notifications')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="bx-btn bx-btn-outline"><?= icon('check') ?> <?= $lang === 'bn' ? 'সবগুলো পঠিত করুন' : 'Mark All as Read' ?></button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="bx-empty-state">
            <?= illustration_svg('empty') ?>
            <h2 class="bx-h2" style="margin-bottom:8px;"><?= $lang === 'bn' ? 'কোনো বিজ্ঞপ্তি নেই' : 'All Caught Up' ?></h2>
            <p class="bx-muted" style="margin:0;"><?= $lang === 'bn' ? 'আপনার নতুন কোনো বিজ্ঞপ্তি আসেনি।' : 'You do not have any notifications yet.' ?></p>
        </div>
    <?php else: ?>
        <div class="bx-notif-page-list">
            <?php foreach ($notifications as $n): ?>
                <?php $unread = !(int) $n['is_read']; ?>
                <article class="bx-card bx-notif-card <?= $unread ? 'is-unread' : '' ?>">
                    <div style="flex:1 1 auto;min-width:0;">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                            <strong style="font-size:1.02rem;"><?= e(bi($n, 'title')) ?></strong>
                            <?php if ($unread): ?>
                                <span class="bx-badge bx-badge-gold"><?= $lang === 'bn' ? 'নতুন' : 'Unread' ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (bi($n, 'body') !== ''): ?>
                            <p class="bx-muted" style="margin:0 0 8px;"><?= e(bi($n, 'body')) ?></p>
                        <?php endif; ?>
                        <small class="bx-muted"><?= e(date('d M Y, H:i', strtotime((string) $n['created_at']))) ?></small>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <?php if (!empty($n['link_url'])): ?>
                            <a href="<?= e(url((string) $n['link_url'])) ?>" class="bx-btn bx-btn-primary bx-btn-sm"><?= $lang === 'bn' ? 'খুলুন' : 'Open' ?></a>
                        <?php endif; ?>
                        <?php if ($unread): ?>
                            <form method="post" action="<?= e(url('/notifications')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                                <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= icon('check') ?> <?= $lang === 'bn' ? 'পঠিত' : 'Mark Read' ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
