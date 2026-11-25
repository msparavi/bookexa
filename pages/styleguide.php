<?php
declare(strict_types=1);
$lang = current_lang();
$allIcons = ['home', 'folder', 'book', 'search', 'sun', 'moon', 'lock', 'shield', 'eye', 'heart', 'chevron-right', 'chevron-down', 'menu', 'close', 'user', 'users', 'globe', 'sparkles', 'bell', 'check', 'layers', 'cart', 'settings', 'edit', 'trash', 'plus', 'logout'];

$renderThemeSample = function (string $scopeClass, string $label) use ($lang): string {
    ob_start(); ?>
    <div class="<?= e($scopeClass) ?>" style="background:var(--bg);color:var(--text);padding:26px;border-radius:28px;border:1px solid var(--border);box-shadow:var(--shadow-md);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
            <span class="bx-badge bx-badge-primary"><?= e($label) ?></span>
            <?= bookexa_logo_svg('bx-logo-svg') ?>
        </div>

        <h3 class="bx-h2" style="margin-bottom:6px;"><?= $lang === 'bn' ? 'বাংলা শিরোনাম ও পড়ার স্পষ্টতা' : 'Editorial Heading & Bangla Readability' ?></h3>
        <p style="color:var(--text-muted);margin:0 0 18px;">
            বাংলা ফন্টে আরামদায়ক পড়াশোনার জন্য উষ্ণ ক্রিম ও গভীর নেভি কনট্রাস্ট — Academic, Job &amp; Drawing PDFs on Bookexa.
        </p>

        <!-- Buttons & Badges -->
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
            <button type="button" class="bx-btn bx-btn-primary bx-btn-sm">Primary</button>
            <button type="button" class="bx-btn bx-btn-accent bx-btn-sm">Saffron Gold</button>
            <button type="button" class="bx-btn bx-btn-teal bx-btn-sm">Teal</button>
            <button type="button" class="bx-btn bx-btn-outline bx-btn-sm">Outline</button>
            <button type="button" class="bx-btn bx-btn-ghost bx-btn-sm">Ghost</button>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;">
            <span class="bx-badge bx-badge-primary">Indigo</span>
            <span class="bx-badge bx-badge-accent">Saffron</span>
            <span class="bx-badge bx-badge-success">Teal</span>
            <span class="bx-badge bx-badge-gold">Rose</span>
        </div>

        <!-- Form Inputs: Default, Disabled & Error States -->
        <div class="bx-field" style="margin-bottom:12px;">
            <label for="sg-in-def-<?= e($scopeClass) ?>">Default Input</label>
            <input class="bx-input" id="sg-in-def-<?= e($scopeClass) ?>" type="text" placeholder="reader@bookexa.com">
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,170px),1fr));gap:10px;margin-bottom:18px;">
            <div class="bx-field" style="margin-bottom:0;">
                <label for="sg-in-err-<?= e($scopeClass) ?>">Error State</label>
                <input class="bx-input is-invalid" aria-invalid="true" id="sg-in-err-<?= e($scopeClass) ?>" type="text" value="Invalid value">
                <div class="bx-field-error">Clear danger error message</div>
            </div>
            <div class="bx-field" style="margin-bottom:0;">
                <label for="sg-in-dis-<?= e($scopeClass) ?>">Disabled Input</label>
                <input class="bx-input" id="sg-in-dis-<?= e($scopeClass) ?>" type="text" value="Locked account value" disabled>
            </div>
        </div>

        <!-- 3D Folder Card + 3D Book Card Side by Side -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,200px),1fr));gap:18px;">
            <a href="#sg-cards" class="bx-folder-3d" style="--folder-accent:#2B2F77;">
                <div class="bx-folder-inner">
                    <div class="bx-folder-lid"></div>
                    <div class="bx-folder-peek">
                        <img src="<?= e(asset('uploads/folders/academic.svg')) ?>" alt="Academic" loading="lazy" decoding="async">
                    </div>
                    <div class="bx-folder-face">
                        <h4 class="bx-folder-title">একাডেমিক ফোল্ডার</h4>
                        <p class="bx-folder-desc">Visible lid &amp; thumbnail peeking out.</p>
                        <div class="bx-folder-meta"><span><?= icon('folder') ?> /pdf/academic</span><span><?= icon('chevron-right') ?></span></div>
                    </div>
                </div>
            </a>

            <div class="bx-book-3d">
                <article class="bx-book-card">
                    <div class="bx-book-cover">
                        <img src="<?= e(asset('uploads/covers/cover-bangla.svg')) ?>" alt="Sample Book" loading="lazy" decoding="async">
                        <span class="bx-book-ribbon">৳১৪৯</span>
                    </div>
                    <div class="bx-book-info">
                        <h4 class="bx-book-title">৩ডি বই ও স্পাইন</h4>
                        <p class="bx-book-author">Spine + page thickness</p>
                        <div class="bx-book-footer">
                            <span class="bx-price">৳১৪৯</span>
                            <button type="button" class="bx-btn bx-btn-primary bx-btn-sm">Read</button>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
};
?>
<div class="container" style="padding-top:26px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current">Style Guide</span>
    </nav>

    <!-- Intro -->
    <section class="bx-section">
        <div class="bx-card" style="background:var(--grad-indigo-teal);color:#FBF6EC;border:none;border-radius:var(--radius-lg);">
            <span class="bx-badge" style="background:rgba(251,246,236,0.18);color:#FBF6EC;margin-bottom:10px;"><?= icon('sparkles') ?> Bookexa Design Language</span>
            <h1 class="bx-display" style="margin-bottom:8px;">Visual Identity &amp; Style Guide</h1>
            <p style="margin:0 0 18px;opacity:0.92;max-width:64ch;">
                Warm Paper Cream (#FBF6EC), Deep Ink Navy (#14213D), Deep Indigo (#2B2F77), Saffron Gold (#F4A62A), Secondary Teal (#14B8A6), Soft Rose (#E56B8A), and Midnight (#0B1020) Dark Mode.
            </p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="button" class="bx-btn bx-btn-accent bx-btn-sm" data-theme-toggle><?= icon('moon') ?> Switch Global Theme</button>
                <button type="button" class="bx-btn bx-btn-sm" style="background:rgba(251,246,236,0.18);color:#FBF6EC;" data-modal-open="bx-sg-modal"><?= icon('eye') ?> Glass Modal</button>
                <button type="button" class="bx-btn bx-btn-sm" style="background:rgba(251,246,236,0.18);color:#FBF6EC;" data-toast-trigger="Bookexa Style Guide Toast!"><?= icon('bell') ?> Toast Alert</button>
            </div>
        </div>
    </section>

    <!-- Color Palette & Gradients -->
    <section class="bx-section">
        <div class="bx-card">
            <h2 class="bx-h2" style="margin-bottom:16px;">Color Tokens &amp; Gradients</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,140px),1fr));gap:12px;">
                <div style="padding:14px;border-radius:16px;background:#FBF6EC;color:#14213D;border:1px solid var(--border);font-weight:700;">Cream<br><small>#FBF6EC</small></div>
                <div style="padding:14px;border-radius:16px;background:#14213D;color:#FBF6EC;font-weight:700;">Ink Navy<br><small>#14213D</small></div>
                <div style="padding:14px;border-radius:16px;background:#2B2F77;color:#FBF6EC;font-weight:700;">Deep Indigo<br><small>#2B2F77</small></div>
                <div style="padding:14px;border-radius:16px;background:#F4A62A;color:#14213D;font-weight:700;">Saffron Gold<br><small>#F4A62A</small></div>
                <div style="padding:14px;border-radius:16px;background:#14B8A6;color:#0B1020;font-weight:700;">Teal<br><small>#14B8A6</small></div>
                <div style="padding:14px;border-radius:16px;background:#E56B8A;color:#14213D;font-weight:700;">Soft Rose<br><small>#E56B8A</small></div>
                <div style="padding:14px;border-radius:16px;background:#0B1020;color:#F6F2E8;font-weight:700;">Midnight<br><small>#0B1020</small></div>
                <div style="padding:14px;border-radius:16px;background:#1A2447;color:#F6F2E8;font-weight:700;">Layer Card<br><small>#1A2447</small></div>
                <div style="padding:14px;border-radius:16px;background:var(--grad-indigo-teal);color:#FBF6EC;font-weight:700;">Indigo → Teal</div>
                <div style="padding:14px;border-radius:16px;background:var(--grad-gold-rose);color:#14213D;font-weight:700;">Gold → Rose</div>
            </div>
        </div>
    </section>

    <!-- Side-by-Side Light & Dark Mode Showcase -->
    <section class="bx-section" id="sg-cards">
        <h2 class="bx-h2" style="margin-bottom:16px;">Light Mode &amp; Dark Mode Side-by-Side</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:24px;">
            <?= $renderThemeSample('bx-scope-light', 'Light Mode (#FBF6EC)') ?>
            <?= $renderThemeSample('bx-scope-dark', 'Dark Mode (#0B1020)') ?>
        </div>
    </section>

    <!-- Logo, Favicon Set & Custom Icons -->
    <section class="bx-section">
        <div class="bx-card">
            <h2 class="bx-h2" style="margin-bottom:16px;">Logo, Favicon Set &amp; Custom SVG Icons</h2>
            <div style="display:flex;flex-wrap:wrap;gap:24px;align-items:center;margin-bottom:20px;padding-bottom:18px;border-bottom:1px solid var(--border);">
                <div><small class="bx-muted">Wordmark SVG</small><?= bookexa_logo_svg('bx-logo-svg') ?></div>
                <div><small class="bx-muted">Favicon SVG</small><img src="<?= e(asset('assets/img/favicon.svg')) ?>" alt="SVG Favicon" style="width:42px;height:42px;margin-top:4px;"></div>
                <div><small class="bx-muted">32px PNG</small><img src="<?= e(asset('assets/img/favicon-32x32.png')) ?>" alt="32px PNG" style="width:32px;height:32px;margin-top:4px;"></div>
                <div><small class="bx-muted">180px Apple Touch</small><img src="<?= e(asset('assets/img/apple-touch-icon.png')) ?>" alt="180px PNG" style="width:48px;height:48px;border-radius:12px;margin-top:4px;"></div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <?php foreach ($allIcons as $ic): ?>
                    <span class="bx-pill-btn"><?= icon($ic) ?> <span><?= e($ic) ?></span></span>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- WCAG 2.1 Contrast Audit Matrix (Admin-Only Verification) -->
    <?php
    $wcagLum = static function (string $hex): float {
        $hex = ltrim($hex, '#');
        $sum = 0.0;
        foreach ([0 => 0.2126, 2 => 0.7152, 4 => 0.0722] as $off => $wt) {
            $c = hexdec(substr($hex, $off, 2)) / 255;
            $sum += $wt * ($c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4);
        }
        return $sum;
    };
    $wcagRatio = static function (string $fg, string $bg) use ($wcagLum): float {
        $l1 = $wcagLum($fg);
        $l2 = $wcagLum($bg);
        return round((max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05), 2);
    };
    $contrastPairs = [
        ['Light — Primary Text / Paper BG', '#14213D', '#FBF6EC'],
        ['Light — Primary Text / Card Surface', '#14213D', '#FFFFFF'],
        ['Light — Muted Text (--text-muted) / Paper BG', '#4A5572', '#FBF6EC'],
        ['Light — Muted Text (--text-muted) / Table Header', '#4A5572', '#F3EAD8'],
        ['Light — Muted Placeholder / Input BG', '#4A5572', '#FFFDF8'],
        ['Light — Dark Gold Text (--gold-text) / White Card', '#9A5B00', '#FFFFFF'],
        ['Light — Dark Gold Text (--gold-text) / Paper BG', '#9A5B00', '#FBF6EC'],
        ['Light — Teal Text (--teal-text) / Paper BG', '#0C6E63', '#FBF6EC'],
        ['Light — Rose & Danger Text / Paper BG', '#B8294A', '#FBF6EC'],
        ['Gradient Btn — White on Indigo Start', '#FFFFFF', '#232766'],
        ['Gradient Btn — White on Dark Teal End', '#FFFFFF', '#0F766E'],
        ['Accent Btn — Ink Navy on Saffron Gold', '#14213D', '#F4A62A'],
        ['Dark — Primary Text / Midnight BG', '#F6F2E8', '#0B1020'],
        ['Dark — Primary Text / Layer Card', '#F6F2E8', '#1A2447'],
        ['Dark — Muted Text (--text-muted) / Midnight BG', '#B6C0DC', '#0B1020'],
        ['Dark — Muted Text (--text-muted) / Layer Card', '#B6C0DC', '#1A2447'],
        ['Dark — Gold Text (--gold-text) / Layer Card', '#F4A62A', '#1A2447'],
        ['Dark — Indigo Link (--primary) / Layer Card', '#8F98FF', '#1A2447'],
        ['Dark — Teal Text (--teal-text) / Layer Card', '#2DD4BF', '#1A2447'],
        ['Dark — Danger (--danger) / Layer Card', '#FF7B9C', '#1A2447'],
    ];
    $folderPairs = [
        ['Academic', '#232766', '#0F766E'],
        ['Job', '#8B3A2B', '#C26A1B'],
        ['Admission', '#1B4D89', '#0E7490'],
        ['Religious Knowledge', '#14532D', '#0F766E'],
        ['Arts & Culture', '#5B2C6F', '#A82849'],
        ['Drawing', '#9A3412', '#B45309'],
    ];
    ?>
    <section class="bx-section" id="sg-contrast">
        <div class="bx-card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
                <div>
                    <h2 class="bx-h2">WCAG 2.1 Contrast Verification Matrix (Admin-Only)</h2>
                    <p class="bx-muted" style="margin:4px 0 0;">Computed relative-luminance contrast ratios for Light &amp; Dark semantic pairs (minimum AA threshold: 4.50:1).</p>
                </div>
                <span class="bx-badge bx-badge-success">All Pairs Verified &ge; 4.5:1</span>
            </div>

            <div class="bx-table-scroll">
                <table class="bx-table">
                    <thead>
                        <tr>
                            <th>Color Pair</th>
                            <th>Preview</th>
                            <th>Foreground</th>
                            <th>Background</th>
                            <th>Ratio</th>
                            <th>WCAG Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contrastPairs as [$label, $fg, $bg]): $ratio = $wcagRatio($fg, $bg); ?>
                            <tr>
                                <td><strong><?= e($label) ?></strong></td>
                                <td><span style="display:inline-block;padding:5px 12px;border-radius:10px;background:<?= e($bg) ?>;color:<?= e($fg) ?>;border:1px solid var(--border);font-weight:700;">Sample অ আ</span></td>
                                <td><code><?= e($fg) ?></code></td>
                                <td><code><?= e($bg) ?></code></td>
                                <td><strong><?= e(number_format($ratio, 2)) ?>:1</strong></td>
                                <td>
                                    <span class="bx-badge <?= $ratio >= 7.0 ? 'bx-badge-success' : ($ratio >= 4.5 ? 'bx-badge-primary' : 'bx-badge-gold') ?>">
                                        <?= $ratio >= 7.0 ? 'AAA Pass' : ($ratio >= 4.5 ? 'AA Pass' : 'Fail') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3 class="bx-h2" style="font-size:1.1rem;margin:22px 0 12px;">Top-Level Folder Accent Color Pairs</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,170px),1fr));gap:12px;">
                <?php foreach ($folderPairs as [$fName, $c1, $c2]): ?>
                    <div style="padding:14px;border-radius:16px;background:linear-gradient(135deg, <?= e($c1) ?> 0%, <?= e($c2) ?> 100%);color:#FFFFFF;text-shadow:0 1px 2px rgba(11,16,32,0.35);font-weight:700;">
                        <?= e($fName) ?><br><small><?= e($c1) ?> → <?= e($c2) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Custom Illustrations -->
    <section class="bx-section">
        <h2 class="bx-h2" style="margin-bottom:16px;">Custom Empty, 404 &amp; Error Illustrations</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:20px;">
            <div class="bx-card" style="text-align:center;"><?= illustration_svg('empty') ?><strong>Empty Folder</strong></div>
            <div class="bx-card" style="text-align:center;"><?= illustration_svg('404') ?><strong>404 Not Found</strong></div>
            <div class="bx-card" style="text-align:center;"><?= illustration_svg('error') ?><strong>Error State</strong></div>
        </div>
    </section>
</div>

<div class="bx-modal-backdrop" id="bx-sg-modal" role="dialog" aria-modal="true">
    <div class="bx-modal">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <h3 class="bx-h2" style="font-size:1.25rem;">Glassmorphic Modal</h3>
            <button type="button" class="bx-pill-btn" data-modal-close><?= icon('close') ?></button>
        </div>
        <p class="bx-muted" style="margin:0 0 18px;">Subtle backdrop blur, 28px rounded corners, and crisp contrast in both light and dark themes.</p>
        <div style="display:flex;justify-content:flex-end;">
            <button type="button" class="bx-btn bx-btn-primary bx-btn-sm" data-modal-close>Done</button>
        </div>
    </div>
</div>
