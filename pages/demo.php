<?php
declare(strict_types=1);

$folders = db_all('SELECT * FROM folders WHERE parent_id IS NULL AND is_active = 1 ORDER BY sort_order ASC');
$items   = db_all('SELECT * FROM items WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 4');
$lang    = current_lang();
?>

<div class="container">
    <!-- 1. Hero & Typography Showcase -->
    <section class="bx-section">
        <div class="bx-card" style="background:linear-gradient(135deg, var(--primary) 0%, #174F50 100%); color:#FAF7F2; border:none;">
            <span class="bx-badge" style="background:rgba(255,255,255,0.16); color:#FAF7F2; margin-bottom:14px;">
                <?= icon('sparkles') ?> <?= e(t('demo_badge')) ?>
            </span>
            <h1 class="bx-display" style="margin-bottom:10px;"><?= e(t('demo_title')) ?></h1>
            <p style="margin:0 0 22px; opacity:0.9; max-width:65ch;"><?= e(t('demo_subtitle')) ?></p>

            <div style="display:flex;flex-wrap:wrap;gap:12px;">
                <button type="button" class="bx-btn bx-btn-accent" data-modal-open="bx-demo-modal">
                    <?= icon('eye') ?> <?= e(t('open_modal')) ?>
                </button>
                <button type="button" class="bx-btn" style="background:rgba(255,255,255,0.16);color:#fff;" data-toast-trigger="<?= e($lang === 'bn' ? 'Bookexa টোস্ট নোটিফিকেশন সক্রিয়!' : 'Bookexa toast notification triggered!') ?>">
                    <?= icon('bell') ?> <?= e(t('show_toast')) ?>
                </button>
                <button type="button" class="bx-btn" style="background:#FAF7F2;color:#0F3D3E;" data-theme-toggle>
                    <?= icon('moon') ?> <?= $lang === 'bn' ? 'থিম পরিবর্তন করুন (Light/Dark)' : 'Toggle Light / Dark Theme' ?>
                </button>
            </div>
        </div>
    </section>

    <!-- 2. Color Tokens, Typography & Custom SVG Icons -->
    <section class="bx-section bx-reveal">
        <div class="bx-card">
            <h2 class="bx-h2" style="margin-bottom:16px;"><?= $lang === 'bn' ? '১. কালার টোকেন, টাইপোগ্রাফি ও আইকন' : '1. Color Tokens, Typography & Icons' ?></h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,140px),1fr));gap:12px;margin-bottom:22px;">
                <div style="padding:14px;border-radius:12px;background:var(--primary);color:var(--primary-ink);font-weight:700;">Primary</div>
                <div style="padding:14px;border-radius:12px;background:var(--accent);color:#fff;font-weight:700;">Accent</div>
                <div style="padding:14px;border-radius:12px;background:var(--gold);color:#181A1F;font-weight:700;">Gold</div>
                <div style="padding:14px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);font-weight:700;">Surface Alt</div>
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:center;padding-top:14px;border-top:1px solid var(--border);">
                <?php foreach (['home', 'folder', 'book', 'search', 'shield', 'lock', 'heart', 'eye', 'globe', 'sparkles'] as $ic): ?>
                    <span class="bx-pill-btn" title="<?= e($ic) ?>"><?= icon($ic) ?> <span><?= e($ic) ?></span></span>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- 3. Buttons, Badges, Breadcrumb & Inputs -->
    <section class="bx-section bx-reveal" id="demo-inputs">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:20px;">
            <div class="bx-card">
                <h2 class="bx-h2" style="margin-bottom:16px;"><?= $lang === 'bn' ? '২. বাটন, ব্যাজ ও ব্রেডক্রাম্ব' : '2. Buttons, Badges & Breadcrumb' ?></h2>
                <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
                    <button type="button" class="bx-btn bx-btn-primary"><?= e(t('btn_primary')) ?></button>
                    <button type="button" class="bx-btn bx-btn-accent"><?= e(t('btn_accent')) ?></button>
                    <button type="button" class="bx-btn bx-btn-outline"><?= e(t('btn_outline')) ?></button>
                    <button type="button" class="bx-btn bx-btn-ghost bx-btn-sm"><?= e(t('btn_ghost')) ?></button>
                </div>

                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;">
                    <span class="bx-badge bx-badge-primary"><?= icon('shield') ?> Watermarked</span>
                    <span class="bx-badge bx-badge-success"><?= e(t('free_badge')) ?></span>
                    <span class="bx-badge bx-badge-accent">-32% OFF</span>
                    <span class="bx-badge bx-badge-gold"><?= e(t('featured_badge')) ?></span>
                </div>

                <nav class="bx-breadcrumb" aria-label="Breadcrumb">
                    <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
                    <?= icon('chevron-right') ?>
                    <a href="#demo-folders"><?= e(t('nav_pdfs')) ?></a>
                    <?= icon('chevron-right') ?>
                    <a href="#demo-folders"><?= $lang === 'bn' ? 'একাডেমিক' : 'Academic' ?></a>
                    <?= icon('chevron-right') ?>
                    <span class="current"><?= $lang === 'bn' ? 'এসএসসি (৯ম-১০ম)' : 'SSC (Class 9-10)' ?></span>
                </nav>
            </div>

            <div class="bx-card">
                <h2 class="bx-h2" style="margin-bottom:16px;"><?= $lang === 'bn' ? '৩. ইনপুট ও ফর্ম ফিল্ড' : '3. Form Inputs & CSRF' ?></h2>
                <form onsubmit="event.preventDefault(); window.bxToast('CSRF-protected form preview submitted!');">
                    <?= csrf_field() ?>
                    <div class="bx-field">
                        <label for="demo-name"><?= $lang === 'bn' ? 'নাম বা ইমেইল' : 'Name or Email' ?></label>
                        <input class="bx-input" id="demo-name" type="text" placeholder="reader@bookexa.com">
                    </div>
                    <div class="bx-field">
                        <label for="demo-folder"><?= $lang === 'bn' ? 'ফোল্ডার নির্বাচন' : 'Select Folder' ?></label>
                        <select class="bx-select" id="demo-folder">
                            <?php foreach ($folders as $f): ?>
                                <option><?= e(bi($f, 'name')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= icon('check') ?> <?= $lang === 'bn' ? 'টেস্ট সাবমিট' : 'Test Submit' ?></button>
                </form>
            </div>
        </div>
    </section>

    <!-- 4. 3D Folder Cards -->
    <section class="bx-section bx-reveal" id="demo-folders">
        <div style="margin-bottom:18px;">
            <h2 class="bx-h2"><?= $lang === 'bn' ? '৪. থ্রি-ডি ফোল্ডার কার্ড (হোভার টিল্ট ও ওপেন অ্যানিমেশন)' : '4. 3D Folder Cards (CSS 3D Tilt & Opening Motion)' ?></h2>
            <p class="bx-muted" style="margin:4px 0 0;"><?= $lang === 'bn' ? 'মাউস নিলে থ্রি-ডি টিল্ট এবং ভেতরের কাগজ বের হয়ে আসে।' : 'Hover for 3D perspective tilt and peeking paper sheets.' ?></p>
        </div>

        <div class="bx-folder-grid">
            <?php foreach ($folders as $folder): ?>
                <a href="#demo-folders" class="bx-folder-3d" style="--folder-accent: <?= e($folder['accent_color'] ?: '#0F3D3E') ?>;">
                    <div class="bx-folder-inner">
                        <div class="bx-folder-tab"></div>
                        <div class="bx-folder-sheet"></div>
                        <div class="bx-folder-face">
                            <div class="bx-folder-thumb">
                                <img src="<?= e(asset($folder['thumbnail'] ?: 'uploads/folders/academic.svg')) ?>" alt="<?= e(bi($folder, 'name')) ?>" loading="lazy">
                            </div>
                            <div class="bx-folder-body">
                                <h3 class="bx-folder-title"><?= e(bi($folder, 'name')) ?></h3>
                                <p class="bx-folder-desc"><?= e(bi($folder, 'description')) ?></p>
                                <div class="bx-folder-meta">
                                    <span><?= icon('folder') ?> /pdf/<?= e($folder['slug']) ?></span>
                                    <span><?= icon('chevron-right') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 5. 3D Book Cards -->
    <section class="bx-section bx-reveal" id="demo-books">
        <div style="margin-bottom:18px;">
            <h2 class="bx-h2"><?= $lang === 'bn' ? '৫. থ্রি-ডি বই / পিডিএফ কার্ড' : '5. 3D Book / PDF Cards' ?></h2>
            <p class="bx-muted" style="margin:4px 0 0;"><?= $lang === 'bn' ? 'বুক স্পাইন শ্যাডো, থ্রি-ডি টিল্ট ও ডাইনামিক প্রাইস ব্যাজ।' : 'Book spine depth, 3D tilt, wishlist toggle, and localized BDT pricing.' ?></p>
        </div>

        <div class="bx-book-grid">
            <?php foreach ($items as $item): ?>
                <?php $isFree = (bool) $item['is_free'] || (float) $item['price'] <= 0; ?>
                <div class="bx-book-3d">
                    <article class="bx-book-card">
                        <div class="bx-book-cover">
                            <img src="<?= e(asset($item['cover'] ?: 'uploads/covers/cover-bangla.svg')) ?>" alt="<?= e(bi($item, 'title')) ?>" loading="lazy">
                            <span class="bx-book-ribbon <?= $isFree ? 'free' : '' ?>">
                                <?= e($isFree ? t('free_badge') : format_bdt((float) $item['price'])) ?>
                            </span>
                            <button type="button" class="bx-wish-btn" aria-label="Wishlist"><?= icon('heart') ?></button>
                        </div>
                        <div class="bx-book-info">
                            <h3 class="bx-book-title"><?= e(bi($item, 'title')) ?></h3>
                            <p class="bx-book-author"><?= e(bi($item, 'author')) ?></p>
                            <div class="bx-book-footer">
                                <div class="bx-price">
                                    <?= e($isFree ? t('free_badge') : format_bdt((float) $item['price'])) ?>
                                    <?php if (!$isFree && !empty($item['original_price'])): ?>
                                        <span class="bx-price-old"><?= e(format_bdt((float) $item['original_price'])) ?></span>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="bx-btn bx-btn-primary bx-btn-sm" data-modal-open="bx-demo-modal">
                                    <?= e(t('btn_ghost')) ?>
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 6. Skeleton Loaders -->
    <section class="bx-section bx-reveal">
        <div style="margin-bottom:18px;">
            <h2 class="bx-h2"><?= $lang === 'bn' ? '৬. স্কেলেটন লোডার' : '6. Skeleton Loaders' ?></h2>
        </div>
        <div class="bx-book-grid">
            <?php for ($i = 0; $i < 3; $i++): ?>
                <div class="bx-card" style="padding:14px;">
                    <div class="bx-skeleton" style="height:170px;margin-bottom:12px;"></div>
                    <div class="bx-skeleton" style="height:18px;width:78%;margin-bottom:8px;"></div>
                    <div class="bx-skeleton" style="height:14px;width:52%;margin-bottom:14px;"></div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div class="bx-skeleton" style="height:20px;width:60px;"></div>
                        <div class="bx-skeleton" style="height:32px;width:86px;border-radius:9px;"></div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </section>
</div>

<!-- Demo Modal -->
<div class="bx-modal-backdrop" id="bx-demo-modal" role="dialog" aria-modal="true" aria-labelledby="bx-modal-title">
    <div class="bx-modal">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h3 id="bx-modal-title" class="bx-h2" style="font-size:1.3rem;"><?= e(t('modal_title')) ?></h3>
            <button type="button" class="bx-pill-btn" data-modal-close aria-label="Close"><?= icon('close') ?></button>
        </div>
        <p class="bx-muted" style="margin:0 0 20px;"><?= e(t('modal_body')) ?></p>
        <div style="display:flex;justify-content:flex-end;gap:10px;">
            <button type="button" class="bx-btn bx-btn-outline bx-btn-sm" data-modal-close><?= e(t('close')) ?></button>
            <button type="button" class="bx-btn bx-btn-primary bx-btn-sm" data-modal-close data-toast-trigger="Bookexa modal confirmed!"><?= icon('check') ?> OK</button>
        </div>
    </div>
</div>
