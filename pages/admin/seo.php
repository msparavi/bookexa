<?php
declare(strict_types=1);
/** @var array $admin, string $lang */

$pageTypes = seo_default_templates();
$redirects = db_all('SELECT * FROM seo_redirects ORDER BY id DESC LIMIT 200');
$logs404   = db_all('SELECT * FROM seo_404_logs ORDER BY hits DESC, last_seen_at DESC LIMIT 100');
$sdResult  = $_SESSION['seo_sd_test_result'] ?? null;
unset($_SESSION['seo_sd_test_result']);
$prefillFrom = safe_internal_path((string) ($_GET['from'] ?? ''), '');
?>

<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
    <div>
        <h1 class="bx-h2" style="margin:0;"><?= $lang === 'bn' ? 'টেকনিক্যাল SEO, মেটা টেমপ্লেট ও রিডাইরেক্ট' : 'Technical SEO, Templates & Redirects' ?></h1>
        <p class="bx-muted" style="margin:4px 0 0;">
            <?= $lang === 'bn' ? 'ভেরিফিকেশন ট্যাগ, মেটা টেমপ্লেট (৩০-৬০ / ৭০-১৫৫ অক্ষর), ৩০০/৪১০ রিডাইরেক্ট এবং স্ট্রাকচার্ড ডেটা টেস্টার।' : 'Search verification tags, length-clamped meta templates, 301/302/410 redirects, 404 logs, and Schema validator.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?= e(url('/sitemap.xml')) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-outline bx-btn-sm">sitemap.xml</a>
        <a href="<?= e(url('/robots.txt')) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-outline bx-btn-sm">robots.txt</a>
        <form method="post" action="<?= e(url('/admin/seo')) ?>" style="margin:0;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="purge_seo_cache">
            <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'সাইটম্যাপ ও OG ক্যাশ রিসেট' : 'Rebuild Sitemaps & OG Cache' ?></button>
        </form>
    </div>
</div>

<!-- 1. General Verification, Regional Defaults, Social Profiles & Default OG Image -->
<form method="post" action="<?= e(url('/admin/seo')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_seo_settings">

    <section class="bx-card" style="margin-bottom:20px;">
        <h2 style="margin:0 0 12px;font-size:1.1rem;font-family:var(--font-serif);">
            <?= $lang === 'bn' ? '১. সার্চ কনসোল ভেরিফিকেশন, অঞ্চল ও সোশ্যাল প্রোফাইল' : '1. Verification Tags, Region & Social Profiles' ?>
        </h2>
        <div class="bx-grid-3" style="gap:12px;margin-bottom:14px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Google Site Verification</label>
                <input type="text" name="seo_google_verify" class="bx-input" value="<?= e(site_setting('seo_google_verify', '')) ?>" placeholder="verification token string">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Bing Site Verification (msvalidate.01)</label>
                <input type="text" name="seo_bing_verify" class="bx-input" value="<?= e(site_setting('seo_bing_verify', '')) ?>" placeholder="bing verification hex code">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ডিফল্ট অঞ্চল (hreflang Region)' : 'Default Region Code' ?></label>
                <input type="text" name="seo_default_region" class="bx-input" maxlength="5" value="<?= e(site_setting('seo_default_region', 'BD')) ?>" placeholder="BD">
            </div>
        </div>

        <div class="bx-grid-2" style="gap:12px;margin-bottom:14px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ডিফল্ট সোশ্যাল / OG ইমেজ (ফলব্যাক)' : 'Default Social Share Image (Fallback 1200x630)' ?></label>
                <input type="file" name="seo_default_og_image_file" accept="image/jpeg,image/png,image/webp" class="bx-input">
                <small class="bx-muted" style="display:block;margin-top:4px;">Current: <code><?= e(site_setting('seo_default_og_image', 'assets/img/apple-touch-icon.png')) ?></code></small>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">Facebook Page URL</label>
                    <input type="url" name="social_facebook" class="bx-input" value="<?= e(site_setting('social_facebook', '')) ?>" placeholder="https://facebook.com/...">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">YouTube URL</label>
                    <input type="url" name="social_youtube" class="bx-input" value="<?= e(site_setting('social_youtube', '')) ?>" placeholder="https://youtube.com/...">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">Instagram URL</label>
                    <input type="url" name="social_instagram" class="bx-input" value="<?= e(site_setting('social_instagram', '')) ?>">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">Telegram URL</label>
                    <input type="url" name="social_telegram" class="bx-input" value="<?= e(site_setting('social_telegram', '')) ?>">
                </div>
            </div>
        </div>

        <div class="bx-grid-3" style="gap:12px;margin-top:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;"><?= $lang === 'bn' ? 'প্রতিষ্ঠার বছর (Entity Founding Year)' : 'Founding Year (Entity Clarity)' ?></label>
                <input type="text" name="site_founding_year" class="bx-input" maxlength="10" value="<?= e(site_setting('site_founding_year', '2024')) ?>" placeholder="2024">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;"><?= $lang === 'bn' ? 'নীতিমালা সর্বশেষ পর্যালোচনা (Last Reviewed)' : 'Policy Last Reviewed Date' ?></label>
                <input type="date" name="legal_last_reviewed" class="bx-input" value="<?= e(site_setting('legal_last_reviewed', date('Y-m-d'))) ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;"><?= $lang === 'bn' ? 'অতিরিক্ত sameAs লিংক (প্রতি লাইনে একটি URL)' : 'Extra sameAs Profile URLs (1 per line)' ?></label>
                <textarea name="seo_sameas_extra" class="bx-input" rows="2" placeholder="https://www.linkedin.com/company/...&#10;https://x.com/..."><?= e(site_setting('seo_sameas_extra', '')) ?></textarea>
            </div>
        </div>
    </section>

    <!-- 2. Per-Page Meta Templates -->
    <section class="bx-card" style="margin-bottom:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
            <h2 style="margin:0;font-size:1.1rem;font-family:var(--font-serif);">
                <?= $lang === 'bn' ? '২. পেজ টাইপ অনুযায়ী মেটা টাইটেল ও ডেসক্রিপশন টেমপ্লেট' : '2. Meta Title & Description Templates' ?>
            </h2>
            <span class="bx-badge">Variables: <code>{title}</code> <code>{author}</code> <code>{folder}</code> <code>{price}</code> <code>{site}</code></span>
        </div>
        <p class="bx-muted" style="margin:0 0 14px;font-size:0.86rem;">
            <?= $lang === 'bn' ? 'স্বয়ংক্রিয়ভাবে টাইটেল ৩০–৬০ অক্ষর এবং ডেসক্রিপশন ৭০–১৫৫ অক্ষরের মধ্যে শব্দ ভেঙে নিরাপদে সীমাবদ্ধ রাখা হয়।' : 'Titles are word-boundary clamped to 30-60 chars and descriptions to 70-155 chars. Per-item admin overrides take precedence.' ?>
        </p>

        <div style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach ($pageTypes as $pKey => $defs): ?>
                <div style="padding:12px;border:1px solid var(--border);border-radius:10px;background:var(--bg-alt);">
                    <strong style="display:block;margin-bottom:8px;color:var(--primary);text-transform:uppercase;font-size:0.82rem;letter-spacing:0.04em;"><?= e(str_replace('_', ' ', $pKey)) ?></strong>
                    <div class="bx-grid-2" style="gap:10px;">
                        <div>
                            <label style="font-size:0.8rem;font-weight:600;">Title Template (BN)</label>
                            <input type="text" name="tpl[<?= e($pKey) ?>][title_bn]" class="bx-input" value="<?= e(site_setting("seo_tpl_{$pKey}_title_bn", $defs['title_bn'])) ?>">
                        </div>
                        <div>
                            <label style="font-size:0.8rem;font-weight:600;">Title Template (EN)</label>
                            <input type="text" name="tpl[<?= e($pKey) ?>][title_en]" class="bx-input" value="<?= e(site_setting("seo_tpl_{$pKey}_title_en", $defs['title_en'])) ?>">
                        </div>
                        <div>
                            <label style="font-size:0.8rem;font-weight:600;">Description Template (BN)</label>
                            <input type="text" name="tpl[<?= e($pKey) ?>][desc_bn]" class="bx-input" value="<?= e(site_setting("seo_tpl_{$pKey}_desc_bn", $defs['desc_bn'])) ?>">
                        </div>
                        <div>
                            <label style="font-size:0.8rem;font-weight:600;">Description Template (EN)</label>
                            <input type="text" name="tpl[<?= e($pKey) ?>][desc_en]" class="bx-input" value="<?= e(site_setting("seo_tpl_{$pKey}_desc_en", $defs['desc_en'])) ?>">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top:14px;">
            <button type="submit" class="bx-btn bx-btn-primary"><?= $lang === 'bn' ? 'SEO সেটিংস ও টেমপ্লেট সংরক্ষণ করুন' : 'Save SEO Settings & Templates' ?></button>
        </div>
    </section>
</form>

<!-- 3. Structured Data Inspector / Tester -->
<section class="bx-card" id="sd-tester" style="margin-bottom:20px;">
    <h2 style="margin:0 0 10px;font-size:1.1rem;font-family:var(--font-serif);">
        <?= $lang === 'bn' ? '৩. স্ট্রাকচার্ড ডেটা টেস্ট ও মেটা ইনস্পেক্টর' : '3. Structured Data Test & Live Head Inspector' ?>
    </h2>
    <form method="post" action="<?= e(url('/admin/seo')) ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="test_structured_data">
        <input type="text" name="inspect_path" class="bx-input" style="flex:1 1 280px;min-width:0;" value="<?= e((string) ($sdResult ? parse_url((string) $sdResult['url'], PHP_URL_PATH) : '/')) ?>" placeholder="/, /pdf, /book/slug, /guides, /authors">
        <button type="submit" class="bx-btn bx-btn-primary"><?= $lang === 'bn' ? 'যাচাই করুন' : 'Inspect Route' ?></button>
    </form>

    <?php if (is_array($sdResult)): ?>
        <div style="margin-top:14px;padding:14px;border:1px solid var(--border);border-radius:10px;background:var(--bg-alt);">
            <?php if (!empty($sdResult['error'])): ?>
                <div class="bx-alert bx-alert-danger"><?= e((string) $sdResult['error']) ?> (HTTP <?= (int) ($sdResult['http_code'] ?? 0) ?>)</div>
            <?php else: ?>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
                    <span class="bx-badge <?= (int) $sdResult['http_code'] === 200 ? 'bx-badge-teal' : '' ?>">HTTP <?= (int) $sdResult['http_code'] ?></span>
                    <span class="bx-badge">Title: <?= (int) $sdResult['title_len'] ?> chars</span>
                    <span class="bx-badge">Desc: <?= (int) $sdResult['desc_len'] ?> chars</span>
                </div>
                <p style="margin:0 0 4px;font-size:0.88rem;"><strong>&lt;title&gt;:</strong> <?= e((string) $sdResult['title']) ?></p>
                <p style="margin:0 0 4px;font-size:0.88rem;"><strong>Meta Description:</strong> <?= e((string) $sdResult['desc']) ?></p>
                <p style="margin:0 0 10px;font-size:0.88rem;"><strong>Canonical:</strong> <code><?= e((string) $sdResult['canonical']) ?></code></p>
                <strong style="display:block;margin-bottom:6px;"><?= count($sdResult['schemas'] ?? []) ?> JSON-LD Schema Block(s):</strong>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach ($sdResult['schemas'] ?? [] as $sc): ?>
                        <details style="padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--surface);">
                            <summary style="cursor:pointer;font-weight:700;">
                                #<?= (int) $sc['index'] ?> — <?= e((string) $sc['type']) ?>
                                <?php if (!empty($sc['valid'])): ?>
                                    <span class="bx-badge bx-badge-teal" style="margin-left:8px;">VALID</span>
                                <?php else: ?>
                                    <span class="bx-badge" style="margin-left:8px;background:var(--rose);color:#fff;"><?= e(implode(', ', $sc['issues'] ?? [])) ?></span>
                                <?php endif; ?>
                            </summary>
                            <pre style="margin:8px 0 0;font-size:0.78rem;overflow:auto;max-height:220px;"><?= e(json_encode($sc['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<!-- 4. Redirect Manager & 404 Log -->
<div class="bx-grid-2" style="align-items:start;">
    <section class="bx-card" id="redirect-manager">
        <h2 style="margin:0 0 12px;font-size:1.1rem;font-family:var(--font-serif);">
            <?= $lang === 'bn' ? '৪. কাস্টম রিডাইরেক্ট ও 410 ম্যানেজার' : '4. Custom Redirects (301 / 302 / 410)' ?>
        </h2>
        <form method="post" action="<?= e(url('/admin/seo')) ?>" style="margin-bottom:14px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_seo_redirect">
            <div class="bx-grid-2" style="gap:10px;margin-bottom:10px;">
                <div>
                    <label style="display:block;font-size:0.84rem;font-weight:600;margin-bottom:4px;">From Path *</label>
                    <input type="text" name="from_path" class="bx-input" required value="<?= e($prefillFrom) ?>" placeholder="/old-page-path">
                </div>
                <div>
                    <label style="display:block;font-size:0.84rem;font-weight:600;margin-bottom:4px;">Status Code</label>
                    <select name="status_code" class="bx-input">
                        <option value="301">301 Moved Permanently</option>
                        <option value="302">302 Temporary Redirect</option>
                        <option value="410">410 Gone (Deleted)</option>
                    </select>
                </div>
            </div>
            <div class="bx-grid-2" style="gap:10px;margin-bottom:10px;">
                <div>
                    <label style="display:block;font-size:0.84rem;font-weight:600;margin-bottom:4px;">Target Path or URL (for 301/302)</label>
                    <input type="text" name="to_path" class="bx-input" placeholder="/new-target-path">
                </div>
                <div>
                    <label style="display:block;font-size:0.84rem;font-weight:600;margin-bottom:4px;">Note</label>
                    <input type="text" name="note" class="bx-input" maxlength="250" placeholder="Optional reason">
                </div>
            </div>
            <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= $lang === 'bn' ? 'রিডাইরেক্ট যোগ করুন' : 'Save Redirect Rule' ?></button>
        </form>

        <div class="bx-table-wrap" style="max-height:360px;overflow:auto;">
            <table class="bx-table bx-mobile-cards">
                <thead><tr><th>From</th><th>Code / To</th><th>Hits</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if (!$redirects): ?>
                        <tr><td colspan="4" class="bx-muted" style="padding:12px;">No custom redirect rules yet.</td></tr>
                    <?php else: foreach ($redirects as $rd): ?>
                        <tr>
                            <td data-label="From"><code><?= e((string) $rd['from_path']) ?></code></td>
                            <td data-label="To">
                                <span class="bx-badge"><?= (int) $rd['status_code'] ?></span>
                                <?= (int) $rd['status_code'] === 410 ? '<small class="bx-muted">Gone</small>' : '<code>' . e((string) $rd['to_path']) . '</code>' ?>
                            </td>
                            <td data-label="Hits"><?= (int) $rd['hits'] ?></td>
                            <td data-label="Action">
                                <form method="post" action="<?= e(url('/admin/seo')) ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_seo_redirect">
                                    <input type="hidden" name="id" value="<?= (int) $rd['id'] ?>">
                                    <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm" style="color:var(--rose-text);">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:12px;">
            <h2 style="margin:0;font-size:1.1rem;font-family:var(--font-serif);">
                <?= $lang === 'bn' ? '৫. 404 এরর লগ (সবচেয়ে বেশি খোঁজা)' : '5. Top 404 Not-Found URLs' ?>
            </h2>
            <?php if ($logs404): ?>
                <form method="post" action="<?= e(url('/admin/seo')) ?>" style="margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear_seo_404">
                    <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm">Clear All</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="bx-table-wrap" style="max-height:440px;overflow:auto;">
            <table class="bx-table bx-mobile-cards">
                <thead><tr><th>Path</th><th>Hits</th><th>Last Seen</th><th>Fix</th></tr></thead>
                <tbody>
                    <?php if (!$logs404): ?>
                        <tr><td colspan="4" class="bx-muted" style="padding:12px;">Zero 404 misses recorded yet.</td></tr>
                    <?php else: foreach ($logs404 as $l4): ?>
                        <tr>
                            <td data-label="Path" style="word-break:break-all;"><code><?= e((string) $l4['path']) ?></code></td>
                            <td data-label="Hits"><strong><?= (int) $l4['hits'] ?></strong></td>
                            <td data-label="Last Seen"><small><?= e((string) $l4['last_seen_at']) ?></small></td>
                            <td data-label="Fix">
                                <a href="<?= e(url('/admin/seo?from=' . rawurlencode((string) $l4['path']) . '#redirect-manager')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">+ 301</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
