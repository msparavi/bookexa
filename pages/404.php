<?php
declare(strict_types=1);
?>
<div class="container" style="padding-top:42px;">
    <div class="bx-empty-state">
        <?= illustration_svg('404') ?>
        <h1 class="bx-display" style="font-size:2rem;margin-bottom:10px;"><?= e(t('not_found_title')) ?></h1>
        <p class="bx-muted" style="margin:0 0 22px;"><?= e(t('not_found_desc')) ?></p>
        <div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap;">
            <a href="<?= e(url('/')) ?>" class="bx-btn bx-btn-primary"><?= e(t('go_home')) ?></a>
            <a href="<?= e(url('/pdf')) ?>" class="bx-btn bx-btn-outline"><?= e(t('nav_pdfs')) ?></a>
        </div>
    </div>
</div>
