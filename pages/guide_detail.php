<?php
declare(strict_types=1);
/** @var array $guide, ?array $authorProfile, string $renderedBodyHtml, array $toc, array $relatedGuides, array $relatedBooks, array $relatedFolders */
$lang = current_lang();
$canonUrl = absolute_url_for_lang('/guides/' . rawurlencode((string) $guide['slug']), $lang);
$catName = $lang === 'bn' ? (string) ($guide['category_bn'] ?: $guide['category_en'] ?? '') : (string) ($guide['category_en'] ?: $guide['category_bn'] ?? '');
$tags = content_parse_tags($guide['tags'] ?? null);
$mins = max(1, (int) ($guide['reading_minutes'] ?? content_reading_time(bi($guide, 'body'))));
$pubDate = num_localize(date('d M Y', strtotime((string) ($guide['publish_at'] ?: $guide['created_at'] ?: 'now')) ?: time()));
$updDate = num_localize(date('d M Y', strtotime((string) ($guide['updated_at'] ?: $guide['created_at'] ?: 'now')) ?: time()));
?>
<div class="container" style="padding-top:20px;padding-bottom:46px;max-width:920px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <a href="<?= e(url('/guides')) ?>"><?= e(t('nav_guides')) ?></a>
        <?php if ($catName !== '' && !empty($guide['category_slug'])): ?>
            <?= icon('chevron-right') ?>
            <a href="<?= e(url('/guides/' . rawurlencode((string) $guide['category_slug']))) ?>"><?= e($catName) ?></a>
        <?php endif; ?>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(bi($guide, 'title')) ?></span>
    </nav>

    <article class="bx-card" style="padding:clamp(18px,3vw,32px);margin-top:16px;">
        <header style="margin-bottom:20px;">
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px;">
                <?php if ($catName !== '' && !empty($guide['category_slug'])): ?>
                    <a href="<?= e(url('/guides/' . rawurlencode((string) $guide['category_slug']))) ?>" class="bx-badge bx-badge-teal" style="text-decoration:none;"><?= e($catName) ?></a>
                <?php endif; ?>
                <span class="bx-badge"><?= e(num_localize($mins)) ?> <?= $lang === 'bn' ? 'মিনিট পড়া' : 'min read' ?></span>
            </div>
            <h1 class="bx-display" style="font-size:clamp(1.45rem,3vw,2.15rem);margin:0 0 12px;line-height:1.28;">
                <?= e(bi($guide, 'title')) ?>
            </h1>
            <div class="bx-muted" style="font-size:0.88rem;display:flex;gap:14px;flex-wrap:wrap;align-items:center;">
                <?php if ($authorProfile): ?>
                    <span><?= $lang === 'bn' ? 'লেখক:' : 'By' ?> <a href="<?= e(url('/authors/' . rawurlencode((string) $authorProfile['slug']))) ?>" style="color:var(--primary);font-weight:700;"><?= e(bi($authorProfile, 'name')) ?></a></span>
                <?php elseif (!empty($guide['author_name'])): ?>
                    <span><?= $lang === 'bn' ? 'লেখক:' : 'By' ?> <strong><?= e((string) $guide['author_name']) ?></strong></span>
                <?php endif; ?>
                <span><?= $lang === 'bn' ? 'প্রকাশ:' : 'Published:' ?> <?= e($pubDate) ?></span>
                <span><strong><?= $lang === 'bn' ? 'সর্বশেষ হালনাগাদ:' : 'Last updated:' ?></strong> <?= e($updDate) ?></span>
            </div>
        </header>

        <?php $shortAns = aeo_short_answer($guide, bi($guide, 'summary') ?: bi($guide, 'body')); ?>
        <?php if ($shortAns !== ''): ?>
            <div class="bx-answer-box" role="note" aria-label="<?= $lang === 'bn' ? 'সংক্ষিপ্ত উত্তর' : 'Quick Summary Answer' ?>">
                <span class="bx-answer-kicker"><?= $lang === 'bn' ? 'সংক্ষিপ্ত উত্তর (একনজরে)' : 'Direct Answer' ?></span>
                <p><?= e($shortAns) ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($guide['cover'])): ?>
            <figure style="margin:0 0 22px;">
                <?= render_responsive_picture((string) $guide['cover'], bi($guide, 'title'), [
                    'width'  => 860,
                    'height' => 440,
                    'sizes'  => '(max-width: 920px) 100vw, 860px',
                    'is_lcp' => true,
                    'style'  => 'width:100%;height:auto;aspect-ratio:860/440;max-height:400px;object-fit:cover;border-radius:var(--radius-md);display:block;',
                ]) ?>
                <figcaption class="bx-muted" style="font-size:0.82rem;margin-top:6px;text-align:center;"><?= e(bi($guide, 'title')) ?></figcaption>
            </figure>
        <?php endif; ?>

        <?php if (!empty($toc)): ?>
            <nav class="bx-toc-box" aria-label="Table of Contents">
                <strong style="font-family:var(--font-serif);font-size:1rem;"><?= $lang === 'bn' ? 'সূচিপত্র (একনজরে)' : 'Table of Contents' ?></strong>
                <ul>
                    <?php foreach ($toc as $item): ?>
                        <li class="toc-l<?= (int) $item['level'] ?>">
                            <a href="#<?= e($item['id']) ?>"><?= e($item['text']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        <?php endif; ?>

        <div class="bx-prose">
            <?= $renderedBodyHtml ?>
        </div>

        <?php if ($tags): ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:22px;">
                <?php foreach ($tags as $tg): ?>
                    <a href="<?= e(url('/guides/tag/' . rawurlencode($tg))) ?>" class="bx-chip" style="text-decoration:none;">#<?= e($tg) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php $guideSources = aeo_parse_sources_json($guide['sources_json'] ?? null); ?>
        <?php if ($guideSources): ?>
            <section class="bx-sources-box" aria-label="<?= $lang === 'bn' ? 'তথ্যসূত্র' : 'Sources and References' ?>">
                <h2 style="font-size:1.05rem;margin:0 0 8px;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'তথ্যসূত্র ও রেফারেন্স (Sources &amp; Citations)' : 'Sources &amp; References' ?></h2>
                <ul style="margin:0;padding-left:20px;line-height:1.7;font-size:0.9rem;">
                    <?php foreach ($guideSources as $src): ?>
                        <li>
                            <?php if ($src['url'] !== ''): ?>
                                <a href="<?= e($src['url']) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--primary);text-decoration:underline;"><?= e($src['title']) ?></a>
                            <?php else: ?>
                                <?= e($src['title']) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php $guideFaqs = content_parse_faqs_json($guide['faqs_json'] ?? null); ?>
        <?php if ($guideFaqs): ?>
            <section class="bx-guide-sub" style="margin-top:24px;">
                <h2 class="bx-h2" style="font-size:1.18rem;margin:0 0 10px;"><?= $lang === 'bn' ? 'গাইড সম্পর্কিত সচরাচর জিজ্ঞাসা (FAQ)' : 'Frequently Asked Questions' ?></h2>
                <?php foreach ($guideFaqs as $fq): ?>
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

        <?php if ($authorProfile): ?>
            <aside class="bx-author-box" aria-label="About the Author">
                <?php if (!empty($authorProfile['photo'])): ?>
                    <img src="<?= e(asset((string) $authorProfile['photo'])) ?>" alt="<?= e(bi($authorProfile, 'name')) ?>" width="72" height="72" style="width:72px;height:72px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                <?php else: ?>
                    <span style="width:68px;height:68px;border-radius:50%;background:var(--primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:1.5rem;flex-shrink:0;">
                        <?= e(mb_substr(bi($authorProfile, 'name'), 0, 1)) ?>
                    </span>
                <?php endif; ?>
                <div style="flex:1 1 240px;">
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:4px;">
                        <a href="<?= e(url('/authors/' . rawurlencode((string) $authorProfile['slug']))) ?>" style="font-weight:800;font-size:1.05rem;color:var(--text);text-decoration:none;"><?= e(bi($authorProfile, 'name')) ?></a>
                        <?php if (bi($authorProfile, 'expertise') !== ''): ?>
                            <span class="bx-badge bx-badge-teal"><?= e(bi($authorProfile, 'expertise')) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (bi($authorProfile, 'bio') !== ''): ?>
                        <p class="bx-muted" style="margin:0 0 8px;font-size:0.9rem;line-height:1.6;"><?= e(bi($authorProfile, 'bio')) ?></p>
                    <?php endif; ?>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;font-size:0.82rem;">
                        <a href="<?= e(url('/authors/' . rawurlencode((string) $authorProfile['slug']))) ?>" style="font-weight:700;color:var(--primary);"><?= $lang === 'bn' ? 'লেখকের সকল গাইড ও বই →' : 'All Guides & Books by Author →' ?></a>
                        <a href="<?= e(url('/editorial-policy')) ?>" class="bx-muted"><?= e(t('legal_editorial')) ?></a>
                    </div>
                </div>
            </aside>
        <?php endif; ?>

        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
            <?= render_social_share_bar($canonUrl, bi($guide, 'title'), t('share_book') . ':') ?>
        </div>
    </article>

    <?php if (!empty($relatedBooks)): ?>
        <section class="bx-guide-sub" style="margin-top:32px;">
            <h2 class="bx-h2" style="margin-bottom:14px;"><?= $lang === 'bn' ? 'প্রস্তুতির সহায়ক বইসমূহ' : 'Recommended Books for This Guide' ?></h2>
            <div class="bx-book-grid" data-stagger>
                <?php foreach ($relatedBooks as $rb): ?>
                    <?php
                    $bUrl = url('/book/' . rawurlencode((string) $rb['slug']));
                    $bCov = !empty($rb['cover']) ? asset((string) $rb['cover']) : asset('uploads/covers/cover-bangla.svg');
                    $isFree = ((int) $rb['is_free'] === 1 || (float) $rb['price'] <= 0);
                    ?>
                    <article class="bx-book-card">
                        <a href="<?= e($bUrl) ?>" class="bx-book-cover-wrap">
                            <img src="<?= e($bCov) ?>" alt="<?= e(bi($rb, 'title')) ?>" width="300" height="400" loading="lazy">
                        </a>
                        <div class="bx-book-body">
                            <a href="<?= e($bUrl) ?>" class="bx-book-title"><?= e(bi($rb, 'title')) ?></a>
                            <div class="bx-book-foot" style="gap:6px;flex-wrap:wrap;">
                                <span class="bx-price"><?= $isFree ? e(t('free_badge')) : e(format_bdt((float) $rb['price'])) ?></span>
                                <?php if ($isFree): ?>
                                    <a href="<?= e(url('/reader/' . rawurlencode((string) $rb['slug']))) ?>" class="bx-btn bx-btn-primary bx-btn-sm"><?= e(t('read_now_btn')) ?></a>
                                <?php else: ?>
                                    <a href="<?= e(url('/checkout/' . rawurlencode((string) $rb['slug']))) ?>" class="bx-btn bx-btn-accent bx-btn-sm"><?= $lang === 'bn' ? 'কিনুন' : 'Buy' ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($relatedFolders)): ?>
        <section class="bx-guide-sub" style="margin-top:28px;">
            <h2 class="bx-h2" style="margin-bottom:14px;"><?= $lang === 'bn' ? 'সম্পর্কিত ফোল্ডার' : 'Related Book Folders' ?></h2>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php foreach ($relatedFolders as $rf): ?>
                    <a href="<?= e(folder_full_url((int) $rf['id'])) ?>" class="bx-chip" style="padding:8px 14px;font-weight:700;text-decoration:none;">
                        <?= e(bi($rf, 'name')) ?> (<?= e(num_localize((int) ($rf['item_count'] ?? 0))) ?>)
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($relatedGuides)): ?>
        <section class="bx-guide-sub" style="margin-top:30px;">
            <h2 class="bx-h2" style="font-size:1.2rem;margin-bottom:14px;"><?= $lang === 'bn' ? 'আরও স্টাডি গাইড' : 'Related Study Guides' ?></h2>
            <div class="bx-folder-grid">
                <?php foreach ($relatedGuides as $rg): ?>
                    <a href="<?= e(url('/guides/' . rawurlencode((string) $rg['slug']))) ?>" class="bx-card" style="padding:14px;text-decoration:none;color:var(--text);">
                        <strong style="display:block;margin-bottom:4px;"><?= e(bi($rg, 'title')) ?></strong>
                        <span class="bx-muted" style="font-size:0.86rem;"><?= e(content_excerpt(bi($rg, 'summary'), 95)) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
