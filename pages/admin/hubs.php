<?php
declare(strict_types=1);
/** @var array $admin, string $lang */

$editHubId = (int) ($_GET['edit_hub'] ?? 0);
$editHub = $editHubId > 0 ? db_one('SELECT * FROM hubs WHERE id = ?', [$editHubId]) : null;
$hubs = db_all('SELECT * FROM hubs ORDER BY sort_order ASC, id DESC');

$allGuides = db_all('SELECT id, title_bn, title_en FROM guides WHERE is_active = 1 ORDER BY id DESC');
$allBooks  = db_all('SELECT id, title_bn, title_en FROM items WHERE is_active = 1 ORDER BY id DESC LIMIT 200');
$folderOptions = build_folder_options();

$selHubBooks   = $editHub ? content_parse_id_list($editHub['featured_item_ids'] ?? null) : [];
$selHubFolders = $editHub ? content_parse_id_list($editHub['featured_folder_ids'] ?? null) : [];
$hubFaqs       = $editHub ? content_parse_faqs_json($editHub['faqs_json'] ?? null) : [];

$globalFaqs = db_all("SELECT * FROM editorial_faqs WHERE scope_type = 'global' ORDER BY sort_order ASC, id ASC");
$orphanReport = content_orphan_pages_report();
?>

<div class="bx-grid-2" style="align-items:start;margin-bottom:26px;">
    <!-- Hub Landing Page Builder -->
    <section class="bx-card" id="hub-form">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h1 class="bx-h2" style="margin:0;"><?= $editHub ? ($lang === 'bn' ? 'হাব পেজ সম্পাদনা' : 'Edit Topic Hub') : ($lang === 'bn' ? 'নতুন টপিক হাব পেজ' : 'Create Topic Hub Page') ?></h1>
            <?php if ($editHub): ?>
                <a href="<?= e(url('/admin/hubs')) ?>" class="bx-btn bx-btn-ghost bx-btn-sm">+ New Hub</a>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= e(url('/admin/hubs')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_hub">
            <?php if ($editHub): ?>
                <input type="hidden" name="id" value="<?= (int) $editHub['id'] ?>">
            <?php endif; ?>

            <div class="bx-grid-2" style="gap:10px;margin-bottom:10px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Title (Bangla) *</label>
                    <input type="text" name="title_bn" class="bx-input" required value="<?= e((string) ($editHub['title_bn'] ?? '')) ?>" placeholder="বিসিএস প্রস্তুতি হাব">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Title (English) *</label>
                    <input type="text" name="title_en" class="bx-input" required data-slug-source value="<?= e((string) ($editHub['title_en'] ?? '')) ?>" placeholder="BCS Preparation Hub">
                </div>
            </div>

            <div class="bx-grid-3" style="gap:10px;margin-bottom:10px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Hub Slug (/hub/{slug})</label>
                    <input type="text" name="slug" class="bx-input" data-slug-target value="<?= e((string) ($editHub['slug'] ?? '')) ?>" placeholder="bcs-preparation">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Pillar Guide</label>
                    <select name="guide_id" class="bx-input">
                        <option value="">— None —</option>
                        <?php foreach ($allGuides as $gd): ?>
                            <option value="<?= (int) $gd['id'] ?>" <?= (int) ($editHub['guide_id'] ?? 0) === (int) $gd['id'] ? 'selected' : '' ?>>
                                <?= e($lang === 'bn' ? $gd['title_bn'] : $gd['title_en']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Sort Order</label>
                    <input type="number" name="sort_order" class="bx-input" value="<?= (int) ($editHub['sort_order'] ?? 0) ?>">
                </div>
            </div>

            <div class="bx-grid-2" style="gap:10px;margin-bottom:10px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">Short Answer (Bangla, 40–60 words)</label>
                    <textarea name="short_answer_bn" rows="2" class="bx-input" placeholder="এই পরীক্ষার প্রস্তুতি কীভাবে শুরু করবেন — ২-৩ বাক্যে উত্তর"><?= e((string) ($editHub['short_answer_bn'] ?? '')) ?></textarea>
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">Short Answer (English, 40–60 words)</label>
                    <textarea name="short_answer_en" rows="2" class="bx-input" placeholder="Direct 40-60 word overview answer shown under heading"><?= e((string) ($editHub['short_answer_en'] ?? '')) ?></textarea>
                </div>
            </div>

            <div style="margin-bottom:10px;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Hub Intro (Bangla)</label>
                <textarea name="intro_bn" rows="4" class="bx-input" data-rich-editor><?= e((string) ($editHub['intro_bn'] ?? '')) ?></textarea>
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Hub Intro (English)</label>
                <textarea name="intro_en" rows="4" class="bx-input" data-rich-editor><?= e((string) ($editHub['intro_en'] ?? '')) ?></textarea>
            </div>

            <div class="bx-grid-2" style="gap:10px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Best Books in Hub</label>
                    <select name="featured_item_ids[]" multiple class="bx-input" size="4">
                        <?php foreach ($allBooks as $bk): ?>
                            <option value="<?= (int) $bk['id'] ?>" <?= in_array((int) $bk['id'], $selHubBooks, true) ? 'selected' : '' ?>>
                                <?= e($lang === 'bn' ? $bk['title_bn'] : $bk['title_en']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Featured Folders in Hub</label>
                    <select name="featured_folder_ids[]" multiple class="bx-input" size="4">
                        <?php foreach ($folderOptions as $fo): ?>
                            <option value="<?= (int) $fo['id'] ?>" <?= in_array((int) $fo['id'], $selHubFolders, true) ? 'selected' : '' ?>>
                                <?= e($fo['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div data-faq-wrap style="margin-bottom:12px;padding:10px;border:1px solid var(--border);border-radius:10px;background:var(--bg-alt);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <strong style="font-size:0.9rem;">Hub FAQs</strong>
                    <button type="button" class="bx-btn bx-btn-outline bx-btn-sm" data-faq-add>+ Add FAQ</button>
                </div>
                <div data-faq-list>
                    <?php foreach ($hubFaqs as $fq): ?>
                        <div class="bx-faq-edit-row" data-faq-row="1">
                            <div class="bx-grid-2" style="gap:8px;margin-bottom:6px;">
                                <input type="text" name="faq_q_bn[]" class="bx-input" value="<?= e($fq['q_bn']) ?>" placeholder="প্রশ্ন (বাংলা)">
                                <input type="text" name="faq_q_en[]" class="bx-input" value="<?= e($fq['q_en']) ?>" placeholder="Question (English)">
                            </div>
                            <div class="bx-grid-2" style="gap:8px;margin-bottom:6px;">
                                <textarea name="faq_a_bn[]" rows="2" class="bx-input" placeholder="উত্তর (বাংলা)"><?= e($fq['a_bn']) ?></textarea>
                                <textarea name="faq_a_en[]" rows="2" class="bx-input" placeholder="Answer (English)"><?= e($fq['a_en']) ?></textarea>
                            </div>
                            <input type="text" name="faq_url[]" class="bx-input" style="margin-bottom:6px;" value="<?= e((string) ($fq['url'] ?? '')) ?>" placeholder="Optional link path (e.g. /faq or /guides/...)">
                            <button type="button" class="bx-btn bx-btn-ghost bx-btn-sm" data-faq-remove style="color:var(--rose-text);">Remove FAQ</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <details style="margin-bottom:12px;padding:10px;border:1px solid var(--border);border-radius:10px;background:var(--bg-alt);">
                <summary style="cursor:pointer;font-weight:700;font-size:0.88rem;">SEO Metadata Overrides</summary>
                <div class="bx-grid-2" style="gap:8px;margin-top:8px;">
                    <input type="text" name="seo_title_bn" class="bx-input" placeholder="SEO Title (BN)" value="<?= e((string) ($editHub['seo_title_bn'] ?? '')) ?>">
                    <input type="text" name="seo_title_en" class="bx-input" placeholder="SEO Title (EN)" value="<?= e((string) ($editHub['seo_title_en'] ?? '')) ?>">
                    <textarea name="seo_description_bn" rows="2" class="bx-input" placeholder="SEO Desc (BN)"><?= e((string) ($editHub['seo_description_bn'] ?? '')) ?></textarea>
                    <textarea name="seo_description_en" rows="2" class="bx-input" placeholder="SEO Desc (EN)"><?= e((string) ($editHub['seo_description_en'] ?? '')) ?></textarea>
                </div>
            </details>

            <label style="display:flex;align-items:center;gap:8px;min-height:40px;font-weight:600;margin-bottom:10px;">
                <input type="checkbox" name="is_active" value="1" <?= !$editHub || (int) $editHub['is_active'] ? 'checked' : '' ?>>
                Active &amp; linked from public Hub directory/footer
            </label>
            <button type="submit" class="bx-btn bx-btn-primary" style="width:100%;"><?= $editHub ? 'Update Hub Page' : 'Save Hub Page' ?></button>
        </form>
    </section>

    <!-- Existing Hubs + Internal Linking Orphan Finder -->
    <div style="display:flex;flex-direction:column;gap:18px;">
        <section class="bx-card">
            <h2 style="margin:0 0 12px;font-size:1.1rem;font-family:var(--font-serif);">Topic Hubs (<?= count($hubs) ?>)</h2>
            <div class="bx-table-wrap">
                <table class="bx-table bx-mobile-cards">
                    <thead><tr><th>Hub</th><th>URL</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if (!$hubs): ?>
                            <tr><td colspan="3" class="bx-muted" style="padding:12px;">No hubs created yet.</td></tr>
                        <?php else: foreach ($hubs as $h): ?>
                            <tr>
                                <td data-label="Hub">
                                    <strong><?= e($lang === 'bn' ? $h['title_bn'] : $h['title_en']) ?></strong>
                                    <span class="bx-badge <?= (int) $h['is_active'] ? 'bx-badge-teal' : '' ?>" style="margin-left:6px;"><?= (int) $h['is_active'] ? 'Active' : 'Hidden' ?></span>
                                </td>
                                <td data-label="URL"><a href="<?= e(url('/hub/' . rawurlencode((string) $h['slug']))) ?>" target="_blank" rel="noopener noreferrer"><code>/hub/<?= e((string) $h['slug']) ?></code></a></td>
                                <td data-label="Action" style="display:flex;gap:6px;">
                                    <a href="<?= e(url('/admin/hubs?edit_hub=' . (int) $h['id'])) ?>" class="bx-btn bx-btn-outline bx-btn-sm">Edit</a>
                                    <form method="post" action="<?= e(url('/admin/hubs')) ?>" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_hub">
                                        <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
                                        <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm" style="color:var(--rose-text);">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Orphan Page Suggestion Tool (< 3 incoming internal links) -->
        <section class="bx-card" id="orphan-finder">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                <h2 style="margin:0;font-size:1.1rem;font-family:var(--font-serif);">
                    <?= $lang === 'bn' ? 'অরফান পেজ ও ইন্টারনাল লিংকিং পরামর্শ (< ৩ লিংক)' : 'Internal Link Audit & Orphan Pages (< 3 Inbound Links)' ?>
                </h2>
                <span class="bx-badge <?= $orphanReport['orphan_count'] > 0 ? 'bx-badge-gold' : 'bx-badge-teal' ?>">
                    <?= (int) $orphanReport['orphan_count'] ?> / <?= (int) $orphanReport['total_pages'] ?> need links
                </span>
            </div>
            <p class="bx-muted" style="margin:0 0 12px;font-size:0.86rem;">
                <?= $lang === 'bn' ? '৩টির কম অভ্যন্তরীণ লিংক থাকা পাতাগুলো গাইড বা হাব পেজের ভেতর থেকে লিংক করলে ক্রল ও র‍্যাংকিং উন্নত হয়।' : 'Pages with fewer than 3 incoming internal links should be linked from relevant Study Guides or Topic Hubs.' ?>
            </p>
            <div class="bx-table-wrap" style="max-height:360px;overflow:auto;">
                <table class="bx-table bx-mobile-cards">
                    <thead><tr><th>Type / Page</th><th>Inbound Links</th><th>Suggested Action</th></tr></thead>
                    <tbody>
                        <?php if (!$orphanReport['orphans']): ?>
                            <tr><td colspan="3" style="padding:12px;color:var(--teal-text);font-weight:700;">Every active page has >= 3 internal links!</td></tr>
                        <?php else: foreach (array_slice($orphanReport['orphans'], 0, 50) as $op): ?>
                            <tr>
                                <td data-label="Page">
                                    <span class="bx-badge"><?= e($op['type']) ?></span>
                                    <strong><?= e(mb_substr((string) $op['title'], 0, 48)) ?></strong>
                                    <div><code><?= e($op['path']) ?></code></div>
                                </td>
                                <td data-label="Inbound"><strong><?= (int) $op['incoming'] ?></strong> / 3</td>
                                <td data-label="Action" style="font-size:0.82rem;">
                                    <?php if ($op['type'] === 'Guide'): ?>
                                        Add link in a <a href="<?= e(url('/admin/hubs')) ?>">Hub Page</a> or cross-link inside a Guide
                                    <?php else: ?>
                                        Attach to <a href="<?= e(url('/admin/guides')) ?>">Related Guides</a> or a <a href="<?= e(url('/admin/hubs')) ?>">Hub Page</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<!-- Global / Study FAQs Manager -->
<section class="bx-card">
    <h2 style="margin:0 0 12px;font-size:1.1rem;font-family:var(--font-serif);">Global Study FAQs (Displayed on /guides &amp; Knowledge Hub)</h2>
    <form method="post" action="<?= e(url('/admin/hubs')) ?>" class="bx-grid-2" style="gap:10px;margin-bottom:16px;padding:12px;border:1px solid var(--border);border-radius:10px;background:var(--bg-alt);">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_editorial_faq">
        <input type="text" name="question_bn" class="bx-input" required placeholder="নতুন প্রশ্ন (বাংলা) *">
        <input type="text" name="question_en" class="bx-input" required placeholder="New Question (English) *">
        <textarea name="answer_bn" rows="2" class="bx-input" required placeholder="উত্তর (বাংলা) *"></textarea>
        <textarea name="answer_en" rows="2" class="bx-input" required placeholder="Answer (English) *"></textarea>
        <div style="grid-column:1 / -1;">
            <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm">+ Add Global FAQ</button>
        </div>
    </form>

    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead><tr><th>Question (BN / EN)</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($globalFaqs as $gf): ?>
                    <tr>
                        <td>
                            <strong><?= e((string) $gf['question_bn']) ?></strong>
                            <div class="bx-muted" style="font-size:0.86rem;"><?= e((string) $gf['question_en']) ?></div>
                        </td>
                        <td>
                            <form method="post" action="<?= e(url('/admin/hubs')) ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_editorial_faq">
                                <input type="hidden" name="id" value="<?= (int) $gf['id'] ?>">
                                <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm" style="color:var(--rose-text);">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
