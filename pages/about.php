<?php
declare(strict_types=1);
$folderCount = (int) (db_one('SELECT COUNT(*) AS c FROM folders WHERE is_active = 1')['c'] ?? 0);
$itemCount   = (int) (db_one('SELECT COUNT(*) AS c FROM items WHERE is_active = 1')['c'] ?? 0);
$lang = current_lang();
?>
<div class="container" style="padding-top:24px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(t('nav_about')) ?></span>
    </nav>

    <div class="bx-card" style="margin-top:20px;padding:34px 28px;">
        <span class="bx-badge bx-badge-primary" style="margin-bottom:12px;"><?= icon('sparkles') ?> <?= e(site_setting('site_name', 'Bookexa')) ?></span>
        <h1 class="bx-display" style="margin-bottom:12px;"><?= e(t('about_title')) ?></h1>
        <p style="font-size:1.05rem;max-width:68ch;margin:0 0 26px;"><?= e(t('about_short_desc')) ?></p>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,210px),1fr));gap:16px;margin-bottom:26px;">
            <div style="padding:20px;border-radius:16px;background:var(--bg-alt);border:1px solid var(--border);">
                <div style="color:var(--primary);margin-bottom:8px;"><?= icon('folder', 'bx-icon-lg') ?></div>
                <strong style="font-size:1.4rem;font-family:var(--font-serif);" data-counter="<?= $folderCount ?>" data-counter-suffix="+"><?= e(num_localize($folderCount)) ?>+</strong>
                <div class="bx-muted"><?= $lang === 'bn' ? 'সাজানো বিষয়ভিত্তিক ফোল্ডার' : 'Organized Topic Folders' ?></div>
            </div>
            <div style="padding:20px;border-radius:16px;background:var(--bg-alt);border:1px solid var(--border);">
                <div style="color:var(--gold-text);margin-bottom:8px;"><?= icon('book', 'bx-icon-lg') ?></div>
                <strong style="font-size:1.4rem;font-family:var(--font-serif);" data-counter="<?= $itemCount ?>" data-counter-suffix="+"><?= e(num_localize($itemCount)) ?>+</strong>
                <div class="bx-muted"><?= $lang === 'bn' ? 'ডিজিটাল বই ও স্টাডি পিডিএফ' : 'Digital Books & Study PDFs' ?></div>
            </div>
            <div style="padding:20px;border-radius:16px;background:var(--bg-alt);border:1px solid var(--border);">
                <div style="color:var(--success);margin-bottom:8px;"><?= icon('shield', 'bx-icon-lg') ?></div>
                <strong style="font-size:1.4rem;font-family:var(--font-serif);" data-counter="100" data-counter-suffix="%">100%</strong>
                <div class="bx-muted"><?= $lang === 'bn' ? 'ওয়াটারমার্ক সুরক্ষিত ক্যানভাস রিডার' : 'Watermarked Canvas Reader' ?></div>
            </div>
        </div>

        <a href="<?= e(url('/pdf')) ?>" class="bx-btn bx-btn-primary"><?= e(t('portal_pdf_cta')) ?> <?= icon('chevron-right') ?></a>
    </div>
</div>
