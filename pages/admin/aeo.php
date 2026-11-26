<?php
declare(strict_types=1);
/**
 * Admin AEO & GEO Optimization Screen (/admin/aeo):
 * - AI Crawler Policy Manager (robots.txt per-bot Allow/Block + noai/noimageai headers)
 * - Machine-readable feeds inspection (/llms.txt, /llms-full.txt, /catalog.json, /guides.json)
 * - AEO/GEO Page Readiness Checker & Top 20 Pages Needing Improvement
 *
 * @var array $aeoCrawlers, array $aeoPolicies, array $aeoReport, string $robotsPreview
 */
$aeoCrawlers ??= aeo_crawler_catalog();
$aeoPolicies ??= aeo_crawler_policies();
$aeoReport ??= aeo_score_all_pages();
$robotsPreview ??= aeo_build_robots_txt();
$lang = current_lang();
$topWeak = $aeoReport['top_to_improve'] ?? [];
$avgScore = (int) ($aeoReport['average_score'] ?? 0);
$totalEval = (int) ($aeoReport['total_evaluated'] ?? 0);
?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
    <div>
        <h1 class="bx-h2" style="margin:0;"><?= $lang === 'bn' ? 'AEO ও GEO অপ্টিমাইজেশন চেক (AI সার্চ ও উদ্ধৃতি)' : 'AEO &amp; GEO Readiness Check &amp; AI Crawler Policy' ?></h1>
        <p class="bx-muted" style="margin:4px 0 0;">
            <?= $lang === 'bn'
                ? 'AI সার্চ ইঞ্জিন (Google AI Overviews, Bing Copilot, ChatGPT Search, Perplexity, Claude) নীতিমালা এবং প্রতিটি পাবলিক পেজের উদ্ধৃতি-যোগ্যতা (০–১০০ স্কোর) পরিচালনা করুন।'
                : 'Manage AI crawler Allow/Block rules in robots.txt, content noai/noimageai directives, llms.txt feeds, and page-level AEO/GEO readiness scores.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?= e(url('/llms.txt')) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-outline bx-btn-sm">llms.txt (BN)</a>
        <a href="<?= e(url('/en/llms.txt')) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-outline bx-btn-sm">llms.txt (EN)</a>
        <a href="<?= e(url('/llms-full.txt')) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-outline bx-btn-sm">llms-full.txt</a>
        <a href="<?= e(url('/catalog.json')) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-outline bx-btn-sm">catalog.json</a>
        <a href="<?= e(url('/guides.json')) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-outline bx-btn-sm">guides.json</a>
        <a href="<?= e(url('/faq')) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-ghost bx-btn-sm">/faq</a>
    </div>
</div>

<!-- 1. Summary KPI Cards -->
<div class="bx-grid-3" style="gap:14px;margin-bottom:22px;">
    <section class="bx-card">
        <div class="bx-muted" style="font-size:0.84rem;font-weight:600;"><?= $lang === 'bn' ? 'গড় AEO/GEO স্কোর' : 'Average AEO/GEO Score' ?></div>
        <div style="font-size:2rem;font-weight:800;font-family:var(--font-serif);color:<?= $avgScore >= 75 ? 'var(--teal-text)' : 'var(--gold-text)' ?>;margin-top:4px;">
            <?= e(num_localize((string) $avgScore)) ?> / <?= e(num_localize('100')) ?>
        </div>
        <small class="bx-muted"><?= e(num_localize((string) $totalEval)) ?> <?= $lang === 'bn' ? 'টি পাবলিক পেজ মূল্যায়িত' : 'public pages evaluated' ?></small>
    </section>
    <section class="bx-card">
        <div class="bx-muted" style="font-size:0.84rem;font-weight:600;"><?= $lang === 'bn' ? 'সক্রিয় AI সার্চ/উদ্ধৃতি বট' : 'Search & Citation Bots' ?></div>
        <?php
        $allowedSearch = 0;
        $blockedTrain = 0;
        foreach ($aeoCrawlers as $cw) {
            $mode = $aeoPolicies[$cw['name']] ?? $cw['default'];
            if ($cw['role'] === 'search' && $mode === 'allow') $allowedSearch++;
            if ($cw['role'] === 'training' && $mode === 'block') $blockedTrain++;
        }
        ?>
        <div style="font-size:2rem;font-weight:800;font-family:var(--font-serif);color:var(--primary);margin-top:4px;">
            <?= e(num_localize((string) $allowedSearch)) ?> Allowed
        </div>
        <small class="bx-muted"><?= e(num_localize((string) $blockedTrain)) ?> <?= $lang === 'bn' ? 'টি ট্রেনিং বট ব্লক করা' : 'training crawlers blocked' ?></small>
    </section>
    <section class="bx-card">
        <div class="bx-muted" style="font-size:0.84rem;font-weight:600;"><?= $lang === 'bn' ? 'সুরক্ষিত বই সুরক্ষা' : 'Paid Book Protection' ?></div>
        <div style="font-size:1.35rem;font-weight:800;font-family:var(--font-serif);margin-top:8px;">
            <?= site_setting('aeo_send_noai_paid', '1') === '1' ? 'noai, noimageai ON' : 'Custom Policy' ?>
        </div>
        <small class="bx-muted"><?= $lang === 'bn' ? 'পূর্ণ PDF টেক্সট কখনও ইনডেক্স বা ফিডে যায় না' : 'Full PDF text is excluded from feeds & crawlers' ?></small>
    </section>
</div>

<!-- 2. Top 20 Pages Needing AEO/GEO Improvement -->
<section class="bx-card" style="margin-bottom:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <div>
            <h2 style="margin:0;font-size:1.12rem;font-family:var(--font-serif);">
                <?= $lang === 'bn' ? 'উন্নতি প্রয়োজন এমন শীর্ষ ২০টি পাবলিক পেজ' : 'Top 20 Pages Needing AEO/GEO Improvement' ?>
            </h2>
            <p class="bx-muted" style="margin:2px 0 0;font-size:0.84rem;">
                <?= $lang === 'bn' ? 'মানদণ্ড: সরাসরি উত্তর (২০), FAQ (২০), লেখক ও তারিখ (১৫), স্কিমা (১৫), দৃশ্যমান টেক্সট (১৫), হেডিং গঠন (১৫)।' : 'Criteria: Short Answer (20), FAQ Present (20), Author & Dates (15), Valid Schema (15), Visible Copy (15), Heading Structure (15).' ?>
            </p>
        </div>
        <a href="<?= e(url('/admin/guides')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">+ <?= $lang === 'bn' ? 'গাইড সম্পাদনা' : 'Manage Guides' ?></a>
    </div>

    <div class="bx-table-wrap" style="max-height:480px;overflow:auto;">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Score</th>
                    <th>Answer</th>
                    <th>FAQ</th>
                    <th>Author/Dates</th>
                    <th>Schema</th>
                    <th>Missing Actions</th>
                    <th>Edit</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$topWeak): ?>
                    <tr><td colspan="8" class="bx-muted" style="padding:12px;">No public pages found to audit.</td></tr>
                <?php else: foreach ($topWeak as $pg): ?>
                    <tr>
                        <td data-label="Page">
                            <span class="bx-badge"><?= e($pg['type']) ?></span>
                            <a href="<?= e(url($pg['url'])) ?>" target="_blank" rel="noopener noreferrer" style="font-weight:700;color:var(--text);text-decoration:none;display:block;margin-top:3px;"><?= e($pg['title']) ?></a>
                            <small class="bx-muted"><code><?= e($pg['url']) ?></code></small>
                        </td>
                        <td data-label="Score">
                            <strong style="color:<?= $pg['score'] >= 80 ? 'var(--teal-text)' : ($pg['score'] >= 55 ? 'var(--gold-text)' : 'var(--rose-text)') ?>;">
                                <?= (int) $pg['score'] ?>/100
                            </strong>
                        </td>
                        <td data-label="Answer"><?= $pg['has_short'] ? '<span style="color:var(--teal-text);font-weight:700;">✓</span>' : '<span style="color:var(--rose-text);">✕</span>' ?></td>
                        <td data-label="FAQ"><?= $pg['has_faq'] ? '<span style="color:var(--teal-text);font-weight:700;">✓</span>' : '<span style="color:var(--rose-text);">✕</span>' ?></td>
                        <td data-label="Author"><?= $pg['has_author'] ? '<span style="color:var(--teal-text);font-weight:700;">✓</span>' : '<span style="color:var(--rose-text);">✕</span>' ?></td>
                        <td data-label="Schema"><?= $pg['schema_ok'] ? '<span style="color:var(--teal-text);font-weight:700;">✓</span>' : '<span style="color:var(--rose-text);">✕</span>' ?></td>
                        <td data-label="Missing">
                            <?php if (empty($pg['missing'])): ?>
                                <span class="bx-badge bx-badge-teal">Ready</span>
                            <?php else: ?>
                                <small class="bx-muted"><?= e(implode(' · ', $pg['missing'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Edit">
                            <a href="<?= e(url($pg['edit_url'])) ?>" class="bx-btn bx-btn-outline bx-btn-sm">Fix</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- 3. AI Crawler Policy Manager & robots.txt Rules -->
<div class="bx-grid-2" style="gap:20px;align-items:start;">
    <section class="bx-card">
        <h2 style="margin:0 0 10px;font-size:1.1rem;font-family:var(--font-serif);">
            <?= $lang === 'bn' ? 'AI ক্রলার ও বট অ্যাক্সেস নীতিমালা' : 'AI Crawler Policy Manager' ?>
        </h2>
        <form method="post" action="<?= e(url('/admin/aeo')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_aeo_policy">

            <div class="bx-table-wrap" style="margin-bottom:14px;">
                <table class="bx-table bx-mobile-cards">
                    <thead><tr><th>Crawler</th><th>Purpose</th><th>Policy</th></tr></thead>
                    <tbody>
                        <?php foreach ($aeoCrawlers as $cw): ?>
                            <?php $curMode = $aeoPolicies[$cw['name']] ?? $cw['default']; ?>
                            <tr>
                                <td data-label="Crawler">
                                    <code><?= e($cw['name']) ?></code>
                                    <div class="bx-muted" style="font-size:0.78rem;"><?= e($cw['vendor']) ?> — <?= e($cw['note']) ?></div>
                                </td>
                                <td data-label="Purpose">
                                    <span class="bx-badge <?= $cw['role'] === 'search' ? 'bx-badge-teal' : '' ?>">
                                        <?= e($cw['role'] === 'search' ? 'Search / Citation' : 'AI Training') ?>
                                    </span>
                                </td>
                                <td data-label="Policy">
                                    <select name="crawler_policy[<?= e($cw['name']) ?>]" class="bx-input" style="min-width:110px;">
                                        <option value="allow" <?= $curMode === 'allow' ? 'selected' : '' ?>>Allow (Index)</option>
                                        <option value="block" <?= $curMode === 'block' ? 'selected' : '' ?>>Block (Disallow /)</option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px;padding:12px;border:1px solid var(--border);border-radius:10px;background:var(--bg-alt);">
                <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
                    <input type="checkbox" name="aeo_send_noai_paid" value="1" <?= site_setting('aeo_send_noai_paid', '1') === '1' ? 'checked' : '' ?>>
                    Send <code>noai, noimageai</code> meta &amp; X-Robots-Tag on paid locked books
                </label>
                <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
                    <input type="checkbox" name="aeo_global_noimageai" value="1" <?= site_setting('aeo_global_noimageai', '0') === '1' ? 'checked' : '' ?>>
                    Send <code>noimageai</code> globally across public pages
                </label>
            </div>

            <button type="submit" class="bx-btn bx-btn-primary"><?= $lang === 'bn' ? 'নীতিমালা সংরক্ষণ ও robots.txt আপডেট' : 'Save AI Policy &amp; Rebuild robots.txt' ?></button>
        </form>
    </section>

    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px;">
            <h2 style="margin:0;font-size:1.1rem;font-family:var(--font-serif);">Live /robots.txt Preview</h2>
            <a href="<?= e(url('/robots.txt')) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-outline bx-btn-sm">Open Raw</a>
        </div>
        <pre style="margin:0;padding:14px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);font-size:0.78rem;line-height:1.55;max-height:540px;overflow:auto;white-space:pre-wrap;"><?= e($robotsPreview) ?></pre>
    </section>
</div>
