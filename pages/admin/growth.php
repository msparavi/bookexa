<?php
declare(strict_types=1);
/** @var array $admin, string $lang */

$days = max(7, min(90, (int) ($_GET['days'] ?? 30)));
$analytics = growth_analytics_summary($days);
$audit = growth_seo_audit_snapshot();
$checklist = growth_search_console_checklist();
$inLogs = growth_indexnow_recent_logs(30);
$inQueueCnt = growth_indexnow_queue_count();
$inKey = growth_indexnow_key();
$inKeyUrl = growth_indexnow_key_url();
$subscribers = db_all('SELECT id, email, lang, status, source_path, verified_at, created_at FROM newsletter_subscribers ORDER BY id DESC LIMIT 100');
$subCounts = [
    'active'  => (int) (db_one("SELECT COUNT(*) AS c FROM newsletter_subscribers WHERE status = 'active'")['c'] ?? 0),
    'pending' => (int) (db_one("SELECT COUNT(*) AS c FROM newsletter_subscribers WHERE status = 'pending'")['c'] ?? 0),
];
$recentLinkChecks = db_all('SELECT * FROM seo_link_checks ORDER BY checked_at DESC LIMIT 25');

$maxDailyViews = 1;
foreach ($analytics['daily'] as $dr) {
    $v = (int) ($dr['views'] ?? 0);
    if ($v > $maxDailyViews) {
        $maxDailyViews = $v;
    }
}
$gscName = site_setting('gsc_verify_file_name', '');
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:18px;">
    <div>
        <h1 class="bx-h2" style="margin:0 0 4px;">
            <?= $lang === 'bn' ? 'ইনডেক্সিং, অ্যানালিটিক্স ও গ্রোথ কনসোল' : 'Indexing, Measurement & Growth Console' ?>
        </h1>
        <p class="bx-muted" style="margin:0;">
            <?= $lang === 'bn'
                ? 'IndexNow পিং, গোপনীয়তা-বান্ধব ভিজিটর অ্যানালিটিক্স, SEO হেলথ ড্যাশবোর্ড, Search Console ও নিউজলেটার।'
                : 'IndexNow instant indexing, privacy-first analytics, SEO health audit, Search Console verification & newsletter growth.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?= e(url('/admin/growth?days=7')) ?>" class="bx-btn <?= $days === 7 ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm">7d</a>
        <a href="<?= e(url('/admin/growth?days=30')) ?>" class="bx-btn <?= $days === 30 ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm">30d</a>
        <a href="<?= e(url('/admin/growth?days=90')) ?>" class="bx-btn <?= $days === 90 ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm">90d</a>
        <a href="<?= e(url('/admin/growth?download=indexable-urls-csv')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">
            <?= $lang === 'bn' ? 'সব URL CSV ডাউনলোড' : 'Download Indexable URLs CSV' ?>
        </a>
    </div>
</div>

<!-- 1. Active SEO Alerts -->
<section class="bx-card" id="seo-alerts" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <h2 class="bx-h3" style="margin:0;">
            <?= $lang === 'bn' ? 'SEO ও ইনডেক্সিং অ্যালার্ট' : 'Structured SEO & Indexing Alerts' ?>
            <span class="bx-badge <?= $audit['open_alerts'] ? 'bx-badge-danger' : 'bx-badge-teal' ?>"><?= count($audit['open_alerts']) ?> open</span>
        </h2>
        <?php if ($audit['open_alerts']): ?>
            <form method="post" action="<?= e(url('/admin/growth')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="resolve_seo_alert">
                <input type="hidden" name="alert_id" value="0">
                <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'সবগুলো সমাধান চিহ্নিত করুন' : 'Resolve All Open' ?></button>
            </form>
        <?php endif; ?>
    </div>
    <?php if (!$audit['open_alerts']): ?>
        <p class="bx-muted" style="margin:0;"><?= $lang === 'bn' ? 'এই মুহূর্তে কোনো সক্রিয় SEO ত্রুটি বা সতর্কতা নেই।' : 'No active sitemap, robots.txt, canonical, 404 spike, or broken-link alerts.' ?></p>
    <?php else: ?>
        <div class="bx-table-wrap">
            <table class="bx-table">
                <thead>
                    <tr>
                        <th>Severity</th>
                        <th>Alert</th>
                        <th>Detail</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($audit['open_alerts'] as $al): ?>
                        <tr>
                            <td><span class="bx-badge <?= $al['severity'] === 'critical' ? 'bx-badge-danger' : 'bx-badge-gold' ?>"><?= e($al['severity']) ?></span></td>
                            <td><strong><?= e($lang === 'bn' ? $al['title_bn'] : $al['title_en']) ?></strong><div class="bx-muted" style="font-size:0.78rem;"><?= e($al['alert_type']) ?></div></td>
                            <td><?= e((string) $al['detail']) ?></td>
                            <td>
                                <form method="post" action="<?= e(url('/admin/growth')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="resolve_seo_alert">
                                    <input type="hidden" name="alert_id" value="<?= (int) $al['id'] ?>">
                                    <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm">Resolve</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- 2. Privacy Analytics Charts & Breakdown + Cookie/GA4/Clarity Settings -->
<div class="bx-kpi-grid" style="margin-bottom:20px;">
    <div class="bx-kpi"><span><?= $lang === 'bn' ? 'পেজ ভিউ' : 'Page Views' ?> (<?= $days ?>d)</span><strong><?= number_format((int) $analytics['totViews']) ?></strong></div>
    <div class="bx-kpi"><span><?= $lang === 'bn' ? 'ইন্টারনাল সার্চ' : 'Internal Searches' ?></span><strong><?= number_format((int) $analytics['totSearches']) ?></strong></div>
    <div class="bx-kpi"><span><?= $lang === 'bn' ? 'শেয়ার ক্লিক' : 'Share Actions' ?></span><strong><?= number_format((int) $analytics['totShares']) ?></strong></div>
    <div class="bx-kpi"><span><?= $lang === 'bn' ? 'গড় পেজ লোড' : 'Avg Load Time' ?></span><strong><?= (int) $analytics['avgLoadMs'] ?> ms</strong></div>
</div>

<div class="bx-grid-2" style="gap:18px;align-items:start;margin-bottom:20px;">
    <section class="bx-card" id="analytics-overview">
        <h2 class="bx-h3" style="margin-top:0;"><?= $lang === 'bn' ? 'দৈনিক ট্রাফিক ও ডিভাইস (DNT সম্মানিত, কোনো PII নেই)' : 'Daily Traffic & Breakdown (DNT Honored, Zero PII)' ?></h2>
        <?php if ($analytics['daily']): ?>
            <div style="display:flex;align-items:flex-end;gap:4px;height:120px;padding:12px 4px 4px;border-bottom:1px solid var(--border);margin-bottom:14px;overflow-x:auto;">
                <?php foreach ($analytics['daily'] as $dr): $hPct = max(6, (int) round(((int) $dr['views'] / $maxDailyViews) * 100)); ?>
                    <div title="<?= e($dr['day'] . ': ' . $dr['views'] . ' views') ?>" style="flex:1 0 12px;max-width:28px;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;">
                        <div style="width:100%;height:<?= $hPct ?>%;background:var(--primary);border-radius:4px 4px 0 0;"></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="bx-muted"><?= $lang === 'bn' ? 'এখনও কোনো পেজভিউ ডেটা নেই।' : 'No first-party pageview events recorded yet for this window.' ?></p>
        <?php endif; ?>

        <div class="bx-grid-3" style="gap:10px;font-size:0.86rem;">
            <div>
                <strong><?= $lang === 'bn' ? 'শীর্ষ রেফারার' : 'Top Referrers' ?></strong>
                <ul style="margin:6px 0 0;padding-left:16px;">
                    <?php foreach (array_slice($analytics['topReferrers'], 0, 5) as $r): ?>
                        <li><?= e($r['ref_host']) ?> (<?= (int) $r['views'] ?>)</li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <strong><?= $lang === 'bn' ? 'ডিভাইস' : 'Devices' ?></strong>
                <ul style="margin:6px 0 0;padding-left:16px;">
                    <?php foreach ($analytics['devices'] as $dv): ?>
                        <li><?= e($dv['device_class']) ?> (<?= (int) $dv['views'] ?>)</li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <strong><?= $lang === 'bn' ? 'দেশ (Accept-Lang)' : 'Country (Header Only)' ?></strong>
                <ul style="margin:6px 0 0;padding-left:16px;">
                    <?php foreach (array_slice($analytics['countries'], 0, 5) as $c): ?>
                        <li><?= e($c['country_code']) ?> (<?= (int) $c['views'] ?>)</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php if ($analytics['topSearches']): ?>
            <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border);font-size:0.86rem;">
                <strong><?= $lang === 'bn' ? 'জনপ্রিয় সার্চ:' : 'Top Internal Searches:' ?></strong>
                <?php foreach (array_slice($analytics['topSearches'], 0, 8) as $sq): ?>
                    <span class="bx-badge bx-badge-teal" style="margin:3px 4px 0 0;"><?= e($sq['search_query']) ?> (<?= (int) $sq['hits'] ?>)</span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="bx-card" id="analytics-settings">
        <h2 class="bx-h3" style="margin-top:0;"><?= $lang === 'bn' ? 'কুকি কনসেন্ট, GA4, Clarity ও IndexNow' : 'Cookie Consent, GA4, Clarity & IndexNow' ?></h2>
        <form method="post" action="<?= e(url('/admin/growth')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_growth_analytics">
            <div class="bx-grid-2" style="gap:10px;margin-bottom:10px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">GA4 Measurement ID</label>
                    <input type="text" name="ga4_measurement_id" class="bx-input" value="<?= e(site_setting('ga4_measurement_id', '')) ?>" placeholder="G-XXXXXXXXXX">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Microsoft Clarity ID</label>
                    <input type="text" name="clarity_project_id" class="bx-input" value="<?= e(site_setting('clarity_project_id', '')) ?>" placeholder="abc123def4">
                </div>
            </div>
            <p class="bx-muted" style="font-size:0.82rem;margin:0 0 10px;">
                <?= $lang === 'bn'
                    ? 'GA4 এবং Clarity স্ক্রিপ্ট কেবল ভিজিটর কুকি ব্যানারে সম্মতি ("সব গ্রহণ করুন") দিলেই লোড হয়।'
                    : 'GA4 & Clarity load only after explicit cookie banner consent. Do Not Track (DNT / GPC) blocks first-party telemetry when enabled.' ?>
            </p>
            <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px;">
                <label class="bx-browse-check"><input type="checkbox" name="analytics_dnt_honor" value="1" <?= site_setting('analytics_dnt_honor', '1') === '1' ? 'checked' : '' ?>> <?= $lang === 'bn' ? 'Do Not Track / GPC হেডার সম্মান করুন' : 'Honor Do Not Track (DNT) & Sec-GPC' ?></label>
                <label class="bx-browse-check"><input type="checkbox" name="cookie_banner_enabled" value="1" <?= site_setting('cookie_banner_enabled', '1') === '1' ? 'checked' : '' ?>> <?= $lang === 'bn' ? 'দ্বিভাষিক কুকি কনসেন্ট ব্যানার চালু রাখুন' : 'Enable Bilingual Cookie Consent Banner' ?></label>
                <label class="bx-browse-check"><input type="checkbox" name="indexnow_enabled" value="1" <?= site_setting('indexnow_enabled', '1') === '1' ? 'checked' : '' ?>> <?= $lang === 'bn' ? 'প্রকাশ/হালনাগাদে স্বয়ংক্রিয় IndexNow পিং' : 'Enable Auto IndexNow Pings on Publish / Update / Delete' ?></label>
            </div>
            <button type="submit" class="bx-btn bx-btn-primary"><?= $lang === 'bn' ? 'সেটিংস সংরক্ষণ করুন' : 'Save Analytics Settings' ?></button>
        </form>
    </section>
</div>

<!-- 3. IndexNow Panel -->
<section class="bx-card" id="indexnow" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <div>
            <h2 class="bx-h3" style="margin:0 0 4px;">IndexNow (Bing, Yandex, Seznam & Partners)</h2>
            <p class="bx-muted" style="margin:0;font-size:0.86rem;">
                Hosted Key File: <a href="<?= e($inKeyUrl) ?>" target="_blank" rel="noopener"><?= e($inKeyUrl) ?></a>
                · Queue: <strong><?= $inQueueCnt ?> URL(s)</strong>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <form method="post" action="<?= e(url('/admin/growth')) ?>" style="display:flex;gap:6px;flex-wrap:wrap;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="indexnow_flush_now">
                <input type="text" name="custom_url" class="bx-input" style="max-width:220px;" placeholder="/book/my-slug (optional)">
                <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm">Ping Queue Now</button>
            </form>
            <form method="post" action="<?= e(url('/admin/growth')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="indexnow_submit_sitemap">
                <button type="submit" class="bx-btn bx-btn-accent bx-btn-sm">Submit Sitemap URL</button>
            </form>
            <form method="post" action="<?= e(url('/admin/growth')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="indexnow_regen_key">
                <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm">Rotate Key</button>
            </form>
        </div>
    </div>

    <div class="bx-table-wrap">
        <table class="bx-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Action</th>
                    <th>URLs</th>
                    <th>HTTP</th>
                    <th>Status</th>
                    <th>Response</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$inLogs): ?>
                    <tr><td colspan="6" class="bx-muted">No IndexNow pings logged yet. Publish or update any book/folder/guide or click "Submit Sitemap URL".</td></tr>
                <?php endif; ?>
                <?php foreach ($inLogs as $lg): ?>
                    <tr>
                        <td><?= e($lg['created_at']) ?></td>
                        <td><?= e($lg['action_type']) ?></td>
                        <td><?= (int) $lg['url_count'] ?></td>
                        <td><?= (int) $lg['http_status'] ?></td>
                        <td><span class="bx-badge <?= $lg['status'] === 'sent' ? 'bx-badge-teal' : ($lg['status'] === 'failed' ? 'bx-badge-danger' : 'bx-badge-gold') ?>"><?= e($lg['status']) ?></span></td>
                        <td class="bx-muted" style="max-width:280px;overflow-wrap:anywhere;"><?= e((string) $lg['response_excerpt']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- 4. SEO Health Dashboard -->
<section class="bx-card" id="seo-health" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <h2 class="bx-h3" style="margin:0;"><?= $lang === 'bn' ? 'SEO হেলথ ও কনটেন্ট আইডিয়া ড্যাশবোর্ড' : 'SEO Health & Search-Miss Content Ideas' ?></h2>
        <form method="post" action="<?= e(url('/admin/growth')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="run_link_check_now">
            <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm">Run Link Checker Batch Now</button>
        </form>
    </div>

    <div class="bx-kpi-grid" style="margin-bottom:16px;">
        <div class="bx-kpi"><span>Missing SEO Overrides</span><strong><?= count($audit['missing_meta']) ?></strong></div>
        <div class="bx-kpi"><span>Duplicate Titles</span><strong><?= count($audit['duplicate_titles']) ?></strong></div>
        <div class="bx-kpi"><span>Thin Content</span><strong><?= count($audit['thin_content']) ?></strong></div>
        <div class="bx-kpi"><span>Orphan / Empty Pages</span><strong><?= count($audit['orphan_pages']) ?></strong></div>
        <div class="bx-kpi"><span>Missing Thumb / Alt</span><strong><?= count($audit['missing_alt']) ?></strong></div>
        <div class="bx-kpi"><span>Broken Links / Chains</span><strong><?= count($audit['broken_links']) + count($audit['redirect_chains']) ?></strong></div>
    </div>

    <div class="bx-grid-2" style="gap:16px;align-items:start;">
        <div>
            <h3 style="margin:0 0 8px;font-size:0.98rem;">Content Ideas (Zero-Result Internal Searches)</h3>
            <div class="bx-table-wrap">
                <table class="bx-table">
                    <thead><tr><th>Search Query</th><th>Hits</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if (!$audit['content_ideas']): ?>
                            <tr><td colspan="3" class="bx-muted">No zero-result searches logged yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($audit['content_ideas'] as $idea): ?>
                            <tr>
                                <td><strong><?= e($idea['query_text']) ?></strong> <span class="bx-muted">(<?= e($idea['lang']) ?>)</span></td>
                                <td><?= (int) $idea['hits'] ?></td>
                                <td><a class="bx-btn bx-btn-outline bx-btn-sm" href="<?= e(url('/admin/guides?prefill_topic=' . rawurlencode((string) $idea['query_text']))) ?>">Write Guide</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h3 style="margin:0 0 8px;font-size:0.98rem;">Top 404 URLs & Slow Pages (&ge; 900ms)</h3>
            <div class="bx-table-wrap">
                <table class="bx-table">
                    <thead><tr><th>Path</th><th>Metric</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach (array_slice($audit['top_404s'], 0, 8) as $nf): ?>
                            <tr>
                                <td><code><?= e($nf['path']) ?></code></td>
                                <td><span class="bx-badge bx-badge-danger"><?= (int) $nf['hits'] ?> 404s</span></td>
                                <td><a class="bx-btn bx-btn-outline bx-btn-sm" href="<?= e(url('/admin/seo?from_path=' . rawurlencode((string) $nf['path']) . '#redirect-manager')) ?>">Add 301</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach (array_slice($audit['slow_pages'], 0, 8) as $sp): ?>
                            <tr>
                                <td><code><?= e($sp['path']) ?></code></td>
                                <td><span class="bx-badge bx-badge-gold"><?= (int) $sp['avg_load_ms'] ?> ms avg</span></td>
                                <td class="bx-muted"><?= (int) $sp['samples'] ?> samples</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$audit['top_404s'] && !$audit['slow_pages']): ?>
                            <tr><td colspan="3" class="bx-muted">No frequent 404 URLs or slow page reports yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="bx-grid-2" style="gap:16px;align-items:start;margin-top:16px;">
        <div>
            <h3 style="margin:0 0 8px;font-size:0.98rem;">Thin / Orphan / Missing Meta / Alt Issues</h3>
            <div class="bx-table-wrap">
                <table class="bx-table">
                    <thead><tr><th>Page</th><th>Issue</th><th>Fix</th></tr></thead>
                    <tbody>
                        <?php
                        $combinedIssues = [];
                        foreach (array_slice($audit['duplicate_titles'], 0, 5) as $dt) {
                            $combinedIssues[] = ['url' => $dt['pages'][0]['url'] ?? '/', 'issue' => 'Duplicate title: ' . $dt['title'] . ' (' . $dt['count'] . 'x)', 'edit' => '/admin/seo'];
                        }
                        foreach (array_slice($audit['thin_content'], 0, 6) as $tc) {
                            $combinedIssues[] = ['url' => $tc['url'], 'issue' => 'Thin content (' . (int) $tc['words'] . ' words)', 'edit' => $tc['edit_url']];
                        }
                        foreach (array_slice($audit['orphan_pages'], 0, 6) as $op) {
                            $combinedIssues[] = ['url' => $op['url'], 'issue' => 'Orphan: ' . $op['reason'], 'edit' => '/admin/folders'];
                        }
                        foreach (array_slice($audit['missing_alt'], 0, 6) as $ma) {
                            $combinedIssues[] = ['url' => $ma['url'], 'issue' => $ma['issue'], 'edit' => '/admin/pdfs'];
                        }
                        foreach (array_slice($audit['missing_meta'], 0, 6) as $mm) {
                            $combinedIssues[] = ['url' => $mm['url'], 'issue' => $mm['reason'], 'edit' => $mm['edit_url']];
                        }
                        if (!$combinedIssues): ?>
                            <tr><td colspan="3" class="bx-muted">Clean! No metadata, thin-content, orphan, or missing-alt issues found.</td></tr>
                        <?php endif;
                        foreach (array_slice($combinedIssues, 0, 15) as $iss): ?>
                            <tr>
                                <td><code><?= e($iss['url']) ?></code></td>
                                <td><?= e($iss['issue']) ?></td>
                                <td><a class="bx-btn bx-btn-outline bx-btn-sm" href="<?= e(url($iss['edit'])) ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h3 style="margin:0 0 8px;font-size:0.98rem;">Background Link Checker &amp; Redirect Chains</h3>
            <div class="bx-table-wrap">
                <table class="bx-table">
                    <thead><tr><th>Target</th><th>Source</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if (!$recentLinkChecks): ?>
                            <tr><td colspan="3" class="bx-muted">Click "Run Link Checker Batch Now" or let lazy traffic scan internal links gradually.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($recentLinkChecks as $lc): ?>
                            <tr>
                                <td><code><?= e($lc['target_url']) ?></code></td>
                                <td class="bx-muted"><?= e($lc['source_path']) ?></td>
                                <td>
                                    <span class="bx-badge <?= $lc['is_broken'] ? 'bx-badge-danger' : ($lc['is_redirect_chain'] ? 'bx-badge-gold' : 'bx-badge-teal') ?>">
                                        HTTP <?= (int) $lc['http_status'] ?><?= (int) $lc['redirect_hops'] > 0 ? ' (' . (int) $lc['redirect_hops'] . ' hops)' : '' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- 5. Search Console Helpers & Newsletter Subscribers -->
<div class="bx-grid-2" style="gap:18px;align-items:start;">
    <section class="bx-card" id="search-console">
        <h2 class="bx-h3" style="margin-top:0;">Search Console Verification &amp; Launch Checklist</h2>
        <form method="post" action="<?= e(url('/admin/growth')) ?>" style="margin-bottom:16px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_search_console">
            <div class="bx-grid-2" style="gap:10px;margin-bottom:10px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Google Meta Verification Token</label>
                    <input type="text" name="seo_google_verify" class="bx-input" value="<?= e(site_setting('seo_google_verify', '')) ?>" placeholder="content attribute token">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Bing Webmaster Meta Token</label>
                    <input type="text" name="seo_bing_verify" class="bx-input" value="<?= e(site_setting('seo_bing_verify', '')) ?>" placeholder="msvalidate.01 token">
                </div>
            </div>
            <div class="bx-grid-2" style="gap:10px;margin-bottom:10px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Verification HTML Filename</label>
                    <input type="text" name="gsc_verify_file_name" class="bx-input" value="<?= e($gscName) ?>" placeholder="google1234567890abcdef.html">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Verification HTML File Content</label>
                    <input type="text" name="gsc_verify_file_content" class="bx-input" value="<?= e(site_setting('gsc_verify_file_content', '')) ?>" placeholder="google-site-verification: google1234.html">
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm">Save Verification</button>
                <?php if ($gscName !== ''): ?>
                    <a class="bx-btn bx-btn-outline bx-btn-sm" href="<?= e(rtrim(BASE_URL, '/') . '/' . ltrim($gscName, '/')) ?>" target="_blank" rel="noopener">Open Hosted /<?= e($gscName) ?></a>
                <?php endif; ?>
            </div>
        </form>

        <ul class="bx-quality-list">
            <?php foreach ($checklist as $chk): ?>
                <li class="<?= $chk['ok'] ? 'is-ok' : 'is-warn' ?>">
                    <?= $chk['ok'] ? '✓' : '⚠' ?> <strong><?= e($chk['label']) ?></strong> — <span class="bx-muted"><?= e($chk['detail']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="bx-card" id="newsletter">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
            <div>
                <h2 class="bx-h3" style="margin:0;">Double Opt-In Newsletter Subscribers</h2>
                <p class="bx-muted" style="margin:2px 0 0;font-size:0.84rem;">Confirmed: <strong><?= $subCounts['active'] ?></strong> · Pending verification: <strong><?= $subCounts['pending'] ?></strong></p>
            </div>
            <a href="<?= e(url('/admin/growth?download=newsletter-csv')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">Export CSV</a>
        </div>
        <div class="bx-table-wrap">
            <table class="bx-table">
                <thead><tr><th>Email</th><th>Lang</th><th>Status</th><th>Source</th></tr></thead>
                <tbody>
                    <?php if (!$subscribers): ?>
                        <tr><td colspan="4" class="bx-muted">No newsletter subscribers yet. Visitors can sign up from the site footer.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($subscribers as $sub): ?>
                        <tr>
                            <td><?= e($sub['email']) ?></td>
                            <td><?= e($sub['lang']) ?></td>
                            <td><span class="bx-badge <?= $sub['status'] === 'active' ? 'bx-badge-teal' : ($sub['status'] === 'unsubscribed' ? 'bx-badge-danger' : 'bx-badge-gold') ?>"><?= e($sub['status']) ?></span></td>
                            <td class="bx-muted"><code><?= e($sub['source_path']) ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
