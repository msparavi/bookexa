<?php
declare(strict_types=1);
/** @var array $authors, int $page, int $totalPages */
$lang = current_lang();
?>
<div class="container" style="padding-top:20px;padding-bottom:36px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(t('nav_authors')) ?></span>
    </nav>

    <div class="bx-section-head" style="margin-top:16px;">
        <div>
            <h1 class="bx-display" style="font-size:clamp(1.5rem,3vw,2.05rem);"><?= e($lang === 'bn' ? 'সকল লেখক ও প্রকাশক' : 'Featured Authors & Writers') ?></h1>
            <p><?= e($lang === 'bn' ? 'লেখক অনুযায়ী সকল ডিজিটাল পিডিএফ ও প্রিন্টেড বই খুঁজে নিন।' : 'Browse digital PDFs and printed books grouped by author.') ?></p>
        </div>
    </div>

    <?php if (empty($authors)): ?>
        <div class="bx-empty-state">
            <?= illustration_svg('empty') ?>
            <h2 class="bx-h2" style="font-size:1.3rem;margin-bottom:6px;"><?= $lang === 'bn' ? 'কোনো লেখক পাওয়া যায়নি' : 'No Authors Found' ?></h2>
            <a href="<?= e(url('/pdf')) ?>" class="bx-btn bx-btn-primary bx-btn-sm"><?= e(t('nav_pdfs')) ?></a>
        </div>
    <?php else: ?>
        <div class="bx-folder-grid" data-stagger>
            <?php foreach ($authors as $au): ?>
                <?php $aUrl = url('/authors/' . rawurlencode((string) $au['slug'])); ?>
                <a href="<?= e($aUrl) ?>" class="bx-card" style="padding:16px;display:flex;align-items:center;gap:14px;text-decoration:none;color:var(--text);">
                    <?php if (!empty($au['photo'])): ?>
                        <img src="<?= e(asset((string) $au['photo'])) ?>" alt="<?= e(bi($au, 'name')) ?>" width="56" height="56" loading="lazy" style="width:56px;height:56px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                    <?php else: ?>
                        <span style="width:56px;height:56px;border-radius:50%;background:var(--surface-2);color:var(--primary);display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:1.25rem;flex-shrink:0;">
                            <?= e(mb_substr(bi($au, 'name'), 0, 1)) ?>
                        </span>
                    <?php endif; ?>
                    <div style="min-width:0;">
                        <h2 style="margin:0 0 4px;font-size:1.05rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e(bi($au, 'name')) ?></h2>
                        <span class="bx-muted" style="font-size:0.84rem;">
                            <?= e(num_localize((int) ($au['books_count'] ?? 0))) ?> <?= e(t('books_count')) ?>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="bx-pagination-links" aria-label="Authors Pagination" style="margin-top:28px;">
                <?php if ($page > 1): ?>
                    <a href="<?= e(url('/authors' . ($page - 1 > 1 ? '?page=' . ($page - 1) : ''))) ?>" class="bx-btn bx-btn-ghost bx-btn-sm">« <?= $lang === 'bn' ? 'আগের' : 'Prev' ?></a>
                <?php endif; ?>
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <a href="<?= e(url('/authors' . ($p > 1 ? '?page=' . $p : ''))) ?>" class="bx-btn bx-btn-sm <?= $p === $page ? 'bx-btn-primary' : 'bx-btn-outline' ?>"><?= e(num_localize($p)) ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= e(url('/authors?page=' . ($page + 1))) ?>" class="bx-btn bx-btn-ghost bx-btn-sm"><?= $lang === 'bn' ? 'পরের' : 'Next' ?> »</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
