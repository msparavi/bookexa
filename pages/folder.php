<?php
declare(strict_types=1);

/**
 * Folder browsing page with sort, filters, and 24/page Load More pagination.
 * @var ?array $currentFolder, array $breadcrumbs, array $subfolders, array $items
 * @var string $slugPrefix, array $filters, array $authors, int $page, int $totalPages, int $totalItems
 */
$lang = current_lang();
$basePath = $currentFolder ? ('/pdf/' . $slugPrefix) : '/pdf';
$actionUrl = url($basePath);
?>
<div class="container" style="padding-top:20px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>" data-breadcrumb-back><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <?php if ($currentFolder === null): ?>
            <span class="current"><?= e(t('nav_pdfs')) ?></span>
        <?php else: ?>
            <a href="<?= e(url('/pdf')) ?>" data-breadcrumb-back><?= e(t('nav_pdfs')) ?></a>
            <?php
            $accum = [];
            $lastIdx = count($breadcrumbs) - 1;
            foreach ($breadcrumbs as $idx => $crumb):
                $accum[] = rawurlencode($crumb['slug']);
            ?>
                <?= icon('chevron-right') ?>
                <?php if ($idx === $lastIdx): ?>
                    <span class="current"><?= e(bi($crumb, 'name')) ?></span>
                <?php else: ?>
                    <a href="<?= e(url('/pdf/' . implode('/', $accum))) ?>" data-breadcrumb-back><?= e(bi($crumb, 'name')) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>

    <div class="bx-section-head" style="margin-top:20px;align-items:flex-start;gap:12px;flex-wrap:wrap;">
        <div style="flex:1 1 280px;min-width:0;">
            <h1 class="bx-h2"><?= e($currentFolder ? bi($currentFolder, 'name') : t('browse_folders_title')) ?></h1>
            <p><?= e($currentFolder ? bi($currentFolder, 'description', t('featured_folders_sub')) : t('featured_folders_sub')) ?></p>
        </div>
        <?php if ($currentFolder): ?>
            <?php
            $uFolderSub = current_user();
            $isSubFolder = $uFolderSub ? growth_user_subscribed_to_folder((int) $uFolderSub['id'], (int) $currentFolder['id']) : false;
            ?>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <?php if ($uFolderSub): ?>
                    <button type="button"
                            class="bx-btn <?= $isSubFolder ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm"
                            data-folder-subscribe="<?= (int) $currentFolder['id'] ?>"
                            aria-pressed="<?= $isSubFolder ? 'true' : 'false' ?>"
                            data-label-on="<?= e($lang === 'bn' ? 'ফলো করা হচ্ছে' : 'Subscribed') ?>"
                            data-label-off="<?= e($lang === 'bn' ? 'নতুন বই এলে জানান' : 'New in this folder') ?>">
                        <?= icon('bell', 'bx-icon') ?>
                        <span><?= $isSubFolder ? ($lang === 'bn' ? 'ফলো করা হচ্ছে' : 'Subscribed') : ($lang === 'bn' ? 'নতুন বই এলে জানান' : 'New in this folder') ?></span>
                    </button>
                <?php else: ?>
                    <a href="<?= e(url('/login?redirect=' . rawurlencode($basePath))) ?>" class="bx-btn bx-btn-outline bx-btn-sm">
                        <?= icon('bell', 'bx-icon') ?>
                        <?= $lang === 'bn' ? 'নতুন বইয়ের অ্যালার্ট পান' : 'Notify on new PDFs' ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($currentFolder): ?>
        <?= render_social_share_bar(absolute_url_for_lang($basePath, $lang), bi($currentFolder, 'name'), '', true) ?>
    <?php endif; ?>

    <?php if ($currentFolder): ?>
        <?php $folderAns = aeo_short_answer($currentFolder, bi($currentFolder, 'intro') ?: bi($currentFolder, 'description')); ?>
        <?php if ($folderAns !== ''): ?>
            <div class="bx-answer-box" style="margin-bottom:18px;">
                <span class="bx-answer-kicker"><?= $lang === 'bn' ? 'একনজরে এই সংগ্রহ' : 'Quick Overview' ?></span>
                <p><?= e($folderAns) ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($currentFolder === null): ?>
        <form method="get" action="<?= e(url('/pdf')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px;max-width:460px;">
            <input class="bx-input" style="flex:1 1 200px;min-width:0;" type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="<?= e(t('filter_placeholder')) ?>" data-suggest-input autocomplete="off">
            <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= icon('search') ?> <?= e(t('nav_search')) ?></button>
        </form>
    <?php else: ?>
        <?= render_filter_sort_bar($actionUrl, $filters, $authors) ?>
    <?php endif; ?>

    <!-- Smooth Loading Skeleton (revealed on folder navigation click) -->
    <div id="bx-folder-skeleton" class="bx-folder-grid" style="display:none;margin-bottom:32px;" aria-hidden="true">
        <?php for ($s = 0; $s < 4; $s++): ?>
            <div class="bx-card" style="padding:14px;">
                <div class="bx-skeleton" style="height:140px;margin-bottom:12px;"></div>
                <div class="bx-skeleton" style="height:18px;width:70%;margin-bottom:8px;"></div>
                <div class="bx-skeleton" style="height:14px;width:45%;"></div>
            </div>
        <?php endfor; ?>
    </div>

    <div id="bx-folder-content">
        <?php if (!empty($subfolders)): ?>
            <?php if ($currentFolder !== null): ?>
                <h2 class="bx-h2" style="font-size:1.25rem;margin-bottom:16px;"><?= e(t('subfolders')) ?></h2>
            <?php endif; ?>
            <div class="bx-folder-grid" data-stagger style="margin-bottom:36px;">
                <?php foreach ($subfolders as $sfIdx => $sf): ?>
                    <?php
                    $href = $slugPrefix !== ''
                        ? url('/pdf/' . $slugPrefix . '/' . rawurlencode($sf['slug']))
                        : url('/pdf/' . rawurlencode($sf['slug']));
                    ?>
                    <?= render_folder_card($sf, $href, ['is_lcp' => ($sfIdx < 2)]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($items)): ?>
            <h2 class="bx-h2" style="font-size:1.25rem;margin-bottom:16px;">
                <?= e(t('pdfs_in_folder')) ?> (<?= e(num_localize($totalItems)) ?>)
            </h2>
            <div class="bx-book-grid" id="bx-folder-books-grid" data-stagger>
                <?php foreach ($items as $itIdx => $it): ?>
                    <?= render_book_card($it, [
                        'highlightQuery' => (string) ($filters['q'] ?? ''),
                        'is_lcp'         => (empty($subfolders) && $itIdx < 2),
                    ]) ?>
                <?php endforeach; ?>
            </div>
            <?= render_browse_pagination($basePath, $filters, $page, $totalPages, $totalItems, 'bx-folder-books-grid') ?>
        <?php elseif (empty($subfolders)): ?>
            <div class="bx-empty-state">
                <?= illustration_svg('empty') ?>
                <h2 class="bx-h2" style="font-size:1.35rem;margin-bottom:6px;"><?= e(t('empty_folder_title')) ?></h2>
                <p class="bx-muted" style="margin:0 0 20px;"><?= e(t('empty_folder_desc')) ?></p>
                <a href="<?= e(url('/pdf')) ?>" class="bx-btn bx-btn-primary bx-btn-sm"><?= e(t('back_to_all_folders')) ?></a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($currentFolder): ?>
        <?php
        $fIntro   = bi($currentFolder, 'intro');
        $fWhoFor  = bi($currentFolder, 'who_for');
        $fFaqs    = content_parse_faqs_json($currentFolder['faqs_json'] ?? null);
        ?>
        <?php if (!empty($popularInFolder)): ?>
            <section style="margin-top:34px;">
                <h2 class="bx-h2" style="font-size:1.25rem;margin-bottom:14px;">
                    <?= $lang === 'bn' ? 'এই ফোল্ডারে জনপ্রিয় বইসমূহ' : 'Popular in This Folder' ?>
                </h2>
                <div class="bx-book-grid" data-stagger>
                    <?php foreach ($popularInFolder as $pi): ?>
                        <?= render_book_card($pi) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($fIntro !== '' || $fWhoFor !== ''): ?>
            <section class="bx-card" style="padding:22px;margin-top:32px;">
                <?php if ($fIntro !== ''): ?>
                    <div class="bx-prose"><?= $fIntro ?></div>
                <?php endif; ?>
                <?php if ($fWhoFor !== ''): ?>
                    <div style="margin-top:16px;padding:14px 16px;border-radius:var(--radius-sm);background:var(--bg-alt);border-left:4px solid var(--teal);">
                        <strong style="display:block;margin-bottom:4px;"><?= $lang === 'bn' ? 'কাদের জন্য এই সংগ্রহ?' : 'Who Is This Collection For?' ?></strong>
                        <p class="bx-muted" style="margin:0;line-height:1.65;"><?= e($fWhoFor) ?></p>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($fFaqs): ?>
            <section style="margin-top:28px;">
                <h2 class="bx-h2" style="font-size:1.2rem;margin-bottom:12px;"><?= $lang === 'bn' ? 'সচরাচর জিজ্ঞাসা (FAQ)' : 'Folder FAQs' ?></h2>
                <?php foreach ($fFaqs as $fq): ?>
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
            </section>
        <?php endif; ?>

        <?php if (!empty($prevFolderUrl) || !empty($nextFolderUrl)): ?>
            <nav style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:28px;padding-top:18px;border-top:1px solid var(--border);" aria-label="Adjacent Folders">
                <?php if (!empty($prevFolderUrl)): ?>
                    <a href="<?= e($prevFolderUrl) ?>" class="bx-btn bx-btn-outline bx-btn-sm">« <?= e($prevFolderName ?? ($lang === 'bn' ? 'আগের ফোল্ডার' : 'Previous Folder')) ?></a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <?php if (!empty($nextFolderUrl)): ?>
                    <a href="<?= e($nextFolderUrl) ?>" class="bx-btn bx-btn-outline bx-btn-sm"><?= e($nextFolderName ?? ($lang === 'bn' ? 'পরের ফোল্ডার' : 'Next Folder')) ?> »</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
