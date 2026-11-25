<?php
declare(strict_types=1);
/** @var string $legalTitle, string $legalHtml, string $legalKey */
$lang = current_lang();
$safeHtml = strip_tags($legalHtml, '<h2><h3><h4><p><ul><ol><li><strong><em><br><a>');
$navLinks = [
    'terms'     => ['url' => '/terms',         'label' => t('legal_terms')],
    'privacy'   => ['url' => '/privacy',       'label' => t('legal_privacy')],
    'refund'    => ['url' => '/refund-policy', 'label' => t('legal_refund')],
    'copyright' => ['url' => '/copyright',     'label' => t('legal_copyright')],
];
?>
<div class="container" style="padding-top:24px;max-width:860px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e($legalTitle) ?></span>
    </nav>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:18px 0 14px;">
        <?php foreach ($navLinks as $k => $info): ?>
            <a href="<?= e(url($info['url'])) ?>" class="bx-btn <?= $legalKey === $k ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm">
                <?= e($info['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <article class="bx-card bx-legal-article">
        <h1 class="bx-display" style="font-size:clamp(1.4rem,2.8vw,2.1rem);margin-bottom:14px;"><?= e($legalTitle) ?></h1>
        <div class="bx-legal-body"><?= $safeHtml ?></div>
    </article>
</div>
