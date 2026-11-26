<?php
declare(strict_types=1);
/** @var array $hub, ?array $primaryGuide, array $featuredBooks, array $featuredFolders, array $hubFaqs, array $otherHubs */
$lang = current_lang();
$introHtml = bi($hub, 'intro');
?>
<div class="container" style="padding-top:20px;padding-bottom:46px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <a href="<?= e(url('/guides')) ?>"><?= e(t('nav_guides')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(bi($hub, 'title')) ?></span>
    </nav>

    <section class="bx-card" style="padding:26px;margin:16px 0 28px;">
        <span class="bx-badge bx-badge-teal" style="margin-bottom:8px;"><?= $lang === 'bn' ? 'প্রস্তুতি হাব' : 'Topic Hub' ?></span>
        <h1 class="bx-display" style="font-size:clamp(1.5rem,3vw,2.15rem);margin:0 0 12px;"><?= e(bi($hub, 'title')) ?></h1>
        <?php $hubAns = aeo_short_answer($hub, $introHtml); ?>
        <?php if ($hubAns !== ''): ?>
            <div class="bx-answer-box" style="margin-bottom:14px;">
                <span class="bx-answer-kicker"><?= $lang === 'bn' ? 'সংক্ষিপ্ত উত্তর' : 'Quick Answer' ?></span>
                <p><?= e($hubAns) ?></p>
            </div>
        <?php endif; ?>
        <?php if ($introHtml !== ''): ?>
            <div class="bx-prose"><?= $introHtml ?></div>
        <?php endif; ?>
    </section>

    <?php if ($primaryGuide): ?>
        <section class="bx-card" style="padding:22px;margin-bottom:30px;border-left:4px solid var(--primary);">
            <span class="bx-badge" style="margin-bottom:6px;"><?= $lang === 'bn' ? 'মূল স্টাডি গাইড' : 'Featured Pillar Guide' ?></span>
            <h2 class="bx-h2" style="margin:0 0 8px;">
                <a href="<?= e(url('/guides/' . rawurlencode((string) $primaryGuide['slug']))) ?>" style="color:var(--text);text-decoration:none;">
                    <?= e(bi($primaryGuide, 'title')) ?>
                </a>
            </h2>
            <p class="bx-muted" style="margin:0 0 14px;line-height:1.65;"><?= e(bi($primaryGuide, 'summary') ?: content_excerpt(bi($primaryGuide, 'body'), 180)) ?></p>
            <a href="<?= e(url('/guides/' . rawurlencode((string) $primaryGuide['slug']))) ?>" class="bx-btn bx-btn-primary bx-btn-sm">
                <?= $lang === 'bn' ? 'সম্পূর্ণ গাইড পড়ুন →' : 'Read Full Roadmap →' ?>
            </a>
        </section>
    <?php endif; ?>

    <?php if (!empty($featuredFolders)): ?>
        <section style="margin-bottom:32px;">
            <h2 class="bx-h2" style="margin-bottom:14px;"><?= $lang === 'bn' ? 'সংশ্লিষ্ট বিষয় ও ফোল্ডার' : 'Essential Subject Folders' ?></h2>
            <div class="bx-folder-grid" data-stagger>
                <?php foreach ($featuredFolders as $ff): ?>
                    <?= render_folder_card($ff, folder_full_url((int) $ff['id'])) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($featuredBooks)): ?>
        <section style="margin-bottom:32px;">
            <h2 class="bx-h2" style="margin-bottom:14px;"><?= $lang === 'bn' ? 'সেরা সহায়ক বই ও পিডিএফ' : 'Top Books & Study PDFs' ?></h2>
            <div class="bx-book-grid" data-stagger>
                <?php foreach ($featuredBooks as $fb): ?>
                    <?= render_book_card($fb) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($hubFaqs)): ?>
        <section style="margin-bottom:32px;">
            <h2 class="bx-h2" style="margin-bottom:14px;"><?= $lang === 'bn' ? 'সচরাচর জিজ্ঞাসা (FAQ)' : 'Frequently Asked Questions' ?></h2>
            <?php foreach ($hubFaqs as $fq): ?>
                <?php $qText = $lang === 'bn' ? ($fq['q_bn'] ?: $fq['q_en']) : ($fq['q_en'] ?: $fq['q_bn']); ?>
                <?php $aText = $lang === 'bn' ? ($fq['a_bn'] ?: $fq['a_en']) : ($fq['a_en'] ?: $fq['a_bn']); ?>
                <?php $fqUrl = trim((string) ($fq['url'] ?? '')); ?>
                <?php if ($qText !== ''): ?>
                    <details class="bx-faq-item" open>
                        <summary><?= e($qText) ?></summary>
                        <p>
                            <?= e($aText) ?>
                            <?php if ($fqUrl !== ''): ?>
                                <a href="<?= e(str_starts_with($fqUrl, 'http') ? $fqUrl : url($fqUrl)) ?>" style="display:inline-block;margin-left:6px;font-weight:700;color:var(--primary);"><?= $lang === 'bn' ? 'বিস্তারিত →' : 'Learn more →' ?></a>
                            <?php endif; ?>
                        </p>
                    </details>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($otherHubs)): ?>
        <section>
            <h2 class="bx-h2" style="font-size:1.2rem;margin-bottom:12px;"><?= $lang === 'bn' ? 'অন্যান্য প্রস্তুতি হাব' : 'Explore More Topic Hubs' ?></h2>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php foreach ($otherHubs as $oh): ?>
                    <a href="<?= e(url('/hub/' . rawurlencode((string) $oh['slug']))) ?>" class="bx-chip" style="padding:8px 14px;font-weight:700;text-decoration:none;"><?= e(bi($oh, 'title')) ?></a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
