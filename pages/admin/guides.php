<?php
declare(strict_types=1);
/** @var array $admin, string $lang */

seo_sync_authors_directory();
$editGuideId = (int) ($_GET['edit_guide'] ?? ($_GET['edit'] ?? 0));
$editGuide = $editGuideId > 0 ? db_one('SELECT * FROM guides WHERE id = ?', [$editGuideId]) : null;
$prefillTopic = !$editGuide ? trim((string) ($_GET['prefill_topic'] ?? '')) : '';
$guides = db_all('SELECT * FROM guides ORDER BY id DESC LIMIT 120');

$editAuthorId = (int) ($_GET['edit_author'] ?? 0);
$editAuthor = $editAuthorId > 0 ? db_one('SELECT * FROM authors WHERE id = ?', [$editAuthorId]) : null;
$authorsList = db_all('SELECT * FROM authors ORDER BY id DESC LIMIT 120');

$allBooks = db_all('SELECT id, title_bn, title_en FROM items WHERE is_active = 1 ORDER BY id DESC LIMIT 200');
$folderOptions = build_folder_options();

$selRelBooks = $editGuide ? content_parse_id_list($editGuide['related_item_ids'] ?? null) : [];
$selRelFolders = $editGuide ? content_parse_id_list($editGuide['related_folder_ids'] ?? null) : [];
?>

<div style="display:grid;grid-template-columns:minmax(0,1.65fr) minmax(260px,0.9fr);gap:20px;align-items:start;margin-bottom:26px;">
    <!-- Guides Rich Form -->
    <section class="bx-card" id="guide-form">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h1 class="bx-h2" style="margin:0;"><?= $editGuide ? ($lang === 'bn' ? 'স্টাডি গাইড সম্পাদনা' : 'Edit Study Guide') : ($lang === 'bn' ? 'নতুন স্টাডি গাইড লিখুন' : 'Write New Study Guide') ?></h1>
            <?php if ($editGuide): ?>
                <a href="<?= e(url('/admin/guides')) ?>" class="bx-btn bx-btn-ghost bx-btn-sm">+ New Guide</a>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= e(url('/admin/guides')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_guide">
            <?php if ($editGuide): ?>
                <input type="hidden" name="id" value="<?= (int) $editGuide['id'] ?>">
            <?php endif; ?>

            <div class="bx-grid-2" style="gap:10px;margin-bottom:10px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Title (Bangla) *</label>
                    <input type="text" name="title_bn" class="bx-input" required value="<?= e((string) ($editGuide['title_bn'] ?? $prefillTopic)) ?>">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Title (English) *</label>
                    <input type="text" name="title_en" class="bx-input" required data-slug-source value="<?= e((string) ($editGuide['title_en'] ?? $prefillTopic)) ?>">
                </div>
            </div>

            <div class="bx-grid-3" style="gap:10px;margin-bottom:10px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">URL Slug</label>
                    <input type="text" name="slug" class="bx-input" data-slug-target value="<?= e((string) ($editGuide['slug'] ?? '')) ?>" placeholder="bcs-preliminary-plan">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Category Slug</label>
                    <input type="text" name="category_slug" class="bx-input" value="<?= e((string) ($editGuide['category_slug'] ?? '')) ?>" placeholder="bcs / ssc / hsc / admission">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Tags (comma separated)</label>
                    <input type="text" name="tags" class="bx-input" value="<?= e((string) ($editGuide['tags'] ?? '')) ?>" placeholder="bcs, bangla, notes">
                </div>
            </div>

            <div class="bx-grid-2" style="gap:10px;margin-bottom:10px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Category Name (Bangla)</label>
                    <input type="text" name="category_bn" class="bx-input" value="<?= e((string) ($editGuide['category_bn'] ?? '')) ?>" placeholder="বিসিএস প্রস্তুতি">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Category Name (English)</label>
                    <input type="text" name="category_en" class="bx-input" value="<?= e((string) ($editGuide['category_en'] ?? '')) ?>" placeholder="BCS Preparation">
                </div>
            </div>

            <div class="bx-grid-3" style="gap:10px;margin-bottom:10px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Author Profile (E-E-A-T)</label>
                    <select name="author_id" class="bx-input">
                        <option value="">— Display Name Only —</option>
                        <?php foreach ($authorsList as $au): ?>
                            <option value="<?= (int) $au['id'] ?>" <?= (int) ($editGuide['author_id'] ?? 0) === (int) $au['id'] ? 'selected' : '' ?>>
                                <?= e($lang === 'bn' ? $au['name_bn'] : $au['name_en']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Author Fallback Name</label>
                    <input type="text" name="author_name" class="bx-input" value="<?= e((string) ($editGuide['author_name'] ?? $admin['name'])) ?>">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Scheduled Publish At</label>
                    <input type="datetime-local" name="publish_at" class="bx-input" value="<?= e(!empty($editGuide['publish_at']) ? date('Y-m-d\TH:i', strtotime((string) $editGuide['publish_at'])) : '') ?>">
                </div>
            </div>

            <div class="bx-grid-2" style="gap:10px;margin-bottom:10px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Short Answer (Bangla, ~40–60 words — Answer-first box)</label>
                    <textarea name="short_answer_bn" rows="2" class="bx-input" maxlength="600" placeholder="গাইডের শিরোনাম বা প্রশ্নের সরাসরি উত্তর ২-৩ বাক্যে"><?= e((string) ($editGuide['short_answer_bn'] ?? '')) ?></textarea>
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Short Answer (English, ~40–60 words)</label>
                    <textarea name="short_answer_en" rows="2" class="bx-input" maxlength="600" placeholder="Concise 40-60 word direct answer placed directly under H1"><?= e((string) ($editGuide['short_answer_en'] ?? '')) ?></textarea>
                </div>
            </div>

            <div class="bx-grid-2" style="gap:10px;margin-bottom:10px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Excerpt / Summary (Bangla)</label>
                    <textarea name="summary_bn" rows="2" class="bx-input" maxlength="380"><?= e((string) ($editGuide['summary_bn'] ?? '')) ?></textarea>
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Excerpt / Summary (English)</label>
                    <textarea name="summary_en" rows="2" class="bx-input" maxlength="380"><?= e((string) ($editGuide['summary_en'] ?? '')) ?></textarea>
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label style="display:block;font-weight:700;margin-bottom:4px;">Rich Body (Bangla — use H2 &amp; H3 for Auto Table of Contents)</label>
                <textarea name="body_bn" rows="8" class="bx-input" data-rich-editor><?= e((string) ($editGuide['body_bn'] ?? '')) ?></textarea>
            </div>

            <div style="margin-bottom:12px;">
                <label style="display:block;font-weight:700;margin-bottom:4px;">Rich Body (English — /en/guides/...)</label>
                <textarea name="body_en" rows="8" class="bx-input" data-rich-editor><?= e((string) ($editGuide['body_en'] ?? '')) ?></textarea>
            </div>

            <div class="bx-grid-2" style="gap:10px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Related Books (hold Ctrl/Cmd for multiple)</label>
                    <select name="related_item_ids[]" multiple class="bx-input" size="4">
                        <?php foreach ($allBooks as $bk): ?>
                            <option value="<?= (int) $bk['id'] ?>" <?= in_array((int) $bk['id'], $selRelBooks, true) ? 'selected' : '' ?>>
                                <?= e($lang === 'bn' ? $bk['title_bn'] : $bk['title_en']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Related Folders</label>
                    <select name="related_folder_ids[]" multiple class="bx-input" size="4">
                        <?php foreach ($folderOptions as $fo): ?>
                            <option value="<?= (int) $fo['id'] ?>" <?= in_array((int) $fo['id'], $selRelFolders, true) ? 'selected' : '' ?>>
                                <?= e($fo['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Sources &amp; References (1 per line — Format: <code>Source Title | https://...</code>)</label>
                <?php
                $srcLines = [];
                foreach (aeo_parse_sources_json($editGuide['sources_json'] ?? null) as $sr) {
                    $srcLines[] = $sr['url'] !== '' ? ($sr['title'] . ' | ' . $sr['url']) : $sr['title'];
                }
                ?>
                <textarea name="sources_raw" rows="3" class="bx-input" placeholder="Bangladesh Public Service Commission (BPSC) | https://www.bpsc.gov.bd&#10;NCTB Official Curriculum | https://nctb.gov.bd"><?= e(implode("\n", $srcLines)) ?></textarea>
            </div>

            <?php $guideFaqs = $editGuide ? content_parse_faqs_json($editGuide['faqs_json'] ?? null) : []; ?>
            <div data-faq-wrap style="margin-bottom:12px;padding:10px;border:1px solid var(--border);border-radius:10px;background:var(--bg-alt);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <strong style="font-size:0.9rem;">Guide FAQs (Answer-first &amp; Schema)</strong>
                    <button type="button" class="bx-btn bx-btn-outline bx-btn-sm" data-faq-add>+ Add FAQ</button>
                </div>
                <div data-faq-list>
                    <?php foreach ($guideFaqs as $fq): ?>
                        <div class="bx-faq-edit-row" data-faq-row="1">
                            <div class="bx-grid-2" style="gap:8px;margin-bottom:6px;">
                                <input type="text" name="faq_q_bn[]" class="bx-input" value="<?= e($fq['q_bn']) ?>" placeholder="প্রশ্ন (বাংলা)">
                                <input type="text" name="faq_q_en[]" class="bx-input" value="<?= e($fq['q_en']) ?>" placeholder="Question (English)">
                            </div>
                            <div class="bx-grid-2" style="gap:8px;margin-bottom:6px;">
                                <textarea name="faq_a_bn[]" rows="2" class="bx-input" placeholder="উত্তর (বাংলা)"><?= e($fq['a_bn']) ?></textarea>
                                <textarea name="faq_a_en[]" rows="2" class="bx-input" placeholder="Answer (English)"><?= e($fq['a_en']) ?></textarea>
                            </div>
                            <input type="text" name="faq_url[]" class="bx-input" style="margin-bottom:6px;" value="<?= e((string) ($fq['url'] ?? '')) ?>" placeholder="Optional link path (e.g. /faq)">
                            <button type="button" class="bx-btn bx-btn-ghost bx-btn-sm" data-faq-remove style="color:var(--rose-text);">Remove FAQ</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <details style="margin-bottom:12px;padding:10px;border:1px solid var(--border);border-radius:10px;background:var(--bg-alt);">
                <summary style="cursor:pointer;font-weight:700;font-size:0.88rem;">SEO Metadata Overrides</summary>
                <div class="bx-grid-2" style="gap:8px;margin-top:8px;">
                    <input type="text" name="seo_title_bn" class="bx-input" placeholder="SEO Title (BN)" value="<?= e((string) ($editGuide['seo_title_bn'] ?? '')) ?>">
                    <input type="text" name="seo_title_en" class="bx-input" placeholder="SEO Title (EN)" value="<?= e((string) ($editGuide['seo_title_en'] ?? '')) ?>">
                    <textarea name="seo_description_bn" rows="2" class="bx-input" placeholder="SEO Description (BN)"><?= e((string) ($editGuide['seo_description_bn'] ?? '')) ?></textarea>
                    <textarea name="seo_description_en" rows="2" class="bx-input" placeholder="SEO Description (EN)"><?= e((string) ($editGuide['seo_description_en'] ?? '')) ?></textarea>
                </div>
            </details>

            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:12px;">
                <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="bx-input" style="flex:1 1 200px;">
                <label style="display:flex;align-items:center;gap:6px;font-weight:600;">
                    <input type="checkbox" name="is_active" value="1" <?= !$editGuide || (int) $editGuide['is_active'] ? 'checked' : '' ?>>
                    Active (Published if schedule reached)
                </label>
            </div>
            <button type="submit" class="bx-btn bx-btn-primary" style="width:100%;"><?= $editGuide ? 'Update Guide' : 'Publish Guide' ?></button>
        </form>
    </section>

    <!-- Live Editorial Content Quality Checklist -->
    <aside style="display:flex;flex-direction:column;gap:16px;">
        <section class="bx-card">
            <h2 style="margin:0 0 6px;font-size:1.05rem;font-family:var(--font-serif);">
                <?= $lang === 'bn' ? 'কনটেন্ট কোয়ালিটি চেকলিস্ট' : 'Content Quality Checklist' ?>
            </h2>
            <small class="bx-muted">Exact duplicate guide text is blocked automatically on publish.</small>
            <ul class="bx-quality-list" data-quality-checklist></ul>
        </section>

        <section class="bx-card">
            <h2 style="margin:0 0 10px;font-size:1.05rem;font-family:var(--font-serif);">Feeds &amp; Editorial Pages</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="<?= e(url('/feed.xml')) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-outline bx-btn-sm">RSS (BN)</a>
                <a href="<?= e(url('/en/feed.xml')) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-outline bx-btn-sm">RSS (EN)</a>
                <a href="<?= e(url('/atom.xml')) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-outline bx-btn-sm">Atom</a>
                <a href="<?= e(url('/guides')) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-ghost bx-btn-sm">/guides</a>
            </div>
        </section>
    </aside>
</div>

<!-- Guides Table -->
<section class="bx-card" style="margin-bottom:26px;">
    <h2 style="margin:0 0 12px;font-size:1.1rem;font-family:var(--font-serif);">All Study Guides (<?= count($guides) ?>)</h2>
    <div class="bx-table-wrap" style="max-height:420px;overflow:auto;">
        <table class="bx-table bx-mobile-cards">
            <thead><tr><th>Guide</th><th>Category / Tags</th><th>Read Time / Publish</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (!$guides): ?>
                    <tr><td colspan="4" class="bx-muted" style="padding:12px;">No guides published yet.</td></tr>
                <?php else: foreach ($guides as $g): ?>
                    <tr>
                        <td data-label="Title">
                            <strong><?= e($lang === 'bn' ? $g['title_bn'] : $g['title_en']) ?></strong>
                            <div><a href="<?= e(url('/guides/' . rawurlencode((string) $g['slug']))) ?>" target="_blank" rel="noopener noreferrer"><code>/guides/<?= e((string) $g['slug']) ?></code></a></div>
                        </td>
                        <td data-label="Category">
                            <?= !empty($g['category_en']) ? '<span class="bx-badge">' . e((string) ($lang === 'bn' ? $g['category_bn'] : $g['category_en'])) . '</span>' : '<span class="bx-muted">—</span>' ?>
                            <?php if (!empty($g['tags'])): ?><small class="bx-muted" style="display:block;margin-top:4px;"><?= e((string) $g['tags']) ?></small><?php endif; ?>
                        </td>
                        <td data-label="Read Time">
                            <span class="bx-badge <?= (int) $g['is_active'] ? 'bx-badge-teal' : '' ?>"><?= (int) $g['is_active'] ? 'Active' : 'Draft' ?></span>
                            <small style="display:block;margin-top:4px;"><?= (int) ($g['reading_minutes'] ?? 3) ?> min read</small>
                            <?php if (!empty($g['publish_at']) && strtotime((string) $g['publish_at']) > time()): ?>
                                <small style="color:var(--gold-text);font-weight:700;">Scheduled: <?= e((string) $g['publish_at']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Action" style="display:flex;gap:6px;flex-wrap:wrap;">
                            <a href="<?= e(url('/admin/guides?edit_guide=' . (int) $g['id'])) ?>" class="bx-btn bx-btn-outline bx-btn-sm">Edit</a>
                            <form method="post" action="<?= e(url('/admin/guides')) ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_guide">
                                <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                                <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm" style="color:var(--rose-text);">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- E-E-A-T Author Profiles Directory & Editor -->
<div class="bx-grid-2" style="align-items:start;">
    <section class="bx-card" id="author-editor">
        <h2 style="margin:0 0 12px;font-size:1.1rem;font-family:var(--font-serif);">
            <?= $editAuthor ? 'Edit Author Profile (E-E-A-T & SEO)' : 'Authors Directory (E-E-A-T)' ?>
        </h2>
        <?php if ($editAuthor): ?>
            <form method="post" action="<?= e(url('/admin/guides')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_author_seo">
                <input type="hidden" name="id" value="<?= (int) $editAuthor['id'] ?>">
                <div class="bx-grid-2" style="gap:10px;margin-bottom:10px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;">Name (BN)</label>
                        <input type="text" name="name_bn" class="bx-input" required value="<?= e((string) $editAuthor['name_bn']) ?>">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;">Name (EN)</label>
                        <input type="text" name="name_en" class="bx-input" required value="<?= e((string) $editAuthor['name_en']) ?>">
                    </div>
                </div>
                <div class="bx-grid-2" style="gap:10px;margin-bottom:10px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;">Expertise / Role (BN)</label>
                        <input type="text" name="expertise_bn" class="bx-input" value="<?= e((string) ($editAuthor['expertise_bn'] ?? '')) ?>">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;">Expertise / Role (EN)</label>
                        <input type="text" name="expertise_en" class="bx-input" value="<?= e((string) ($editAuthor['expertise_en'] ?? '')) ?>">
                    </div>
                </div>
                <div style="margin-bottom:10px;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;">URL Slug (/authors/{slug})</label>
                    <input type="text" name="slug" class="bx-input" required value="<?= e((string) $editAuthor['slug']) ?>">
                </div>
                <div class="bx-grid-3" style="gap:8px;margin-bottom:10px;">
                    <input type="url" name="social_facebook" class="bx-input" placeholder="Facebook URL" value="<?= e((string) ($editAuthor['social_facebook'] ?? '')) ?>">
                    <input type="url" name="social_linkedin" class="bx-input" placeholder="LinkedIn URL" value="<?= e((string) ($editAuthor['social_linkedin'] ?? '')) ?>">
                    <input type="url" name="social_website" class="bx-input" placeholder="Website URL" value="<?= e((string) ($editAuthor['social_website'] ?? '')) ?>">
                </div>
                <div class="bx-grid-2" style="gap:10px;margin-bottom:10px;">
                    <textarea name="bio_bn" rows="2" class="bx-input" placeholder="Author Bio (BN)"><?= e((string) ($editAuthor['bio_bn'] ?? '')) ?></textarea>
                    <textarea name="bio_en" rows="2" class="bx-input" placeholder="Author Bio (EN)"><?= e((string) ($editAuthor['bio_en'] ?? '')) ?></textarea>
                    <input type="text" name="seo_title_bn" class="bx-input" placeholder="SEO Title (BN)" value="<?= e((string) ($editAuthor['seo_title_bn'] ?? '')) ?>">
                    <input type="text" name="seo_title_en" class="bx-input" placeholder="SEO Title (EN)" value="<?= e((string) ($editAuthor['seo_title_en'] ?? '')) ?>">
                    <textarea name="seo_description_bn" rows="2" class="bx-input" placeholder="SEO Desc (BN)"><?= e((string) ($editAuthor['seo_description_bn'] ?? '')) ?></textarea>
                    <textarea name="seo_description_en" rows="2" class="bx-input" placeholder="SEO Desc (EN)"><?= e((string) ($editAuthor['seo_description_en'] ?? '')) ?></textarea>
                </div>
                <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px;">
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="bx-input">
                    <label style="display:flex;align-items:center;gap:6px;font-weight:600;">
                        <input type="checkbox" name="is_active" value="1" <?= (int) $editAuthor['is_active'] ? 'checked' : '' ?>> Active
                    </label>
                </div>
                <button type="submit" class="bx-btn bx-btn-primary">Update Author Profile</button>
            </form>
        <?php else: ?>
            <p class="bx-muted" style="margin:0;">Authors are automatically synced from books and guides. Click Edit on any author to add expertise, social links, photo, bio, or SEO metadata.</p>
        <?php endif; ?>
    </section>

    <section class="bx-card">
        <h2 style="margin:0 0 12px;font-size:1.1rem;font-family:var(--font-serif);">Authors (<?= count($authorsList) ?>)</h2>
        <div class="bx-table-wrap" style="max-height:360px;overflow:auto;">
            <table class="bx-table bx-mobile-cards">
                <thead><tr><th>Author</th><th>Slug</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if (!$authorsList): ?>
                        <tr><td colspan="3" class="bx-muted" style="padding:12px;">No authors indexed yet.</td></tr>
                    <?php else: foreach ($authorsList as $au): ?>
                        <tr>
                            <td data-label="Author">
                                <strong><?= e($lang === 'bn' ? $au['name_bn'] : $au['name_en']) ?></strong>
                                <?php if (!empty($au['expertise_en'])): ?><small class="bx-muted" style="display:block;"><?= e((string) ($lang === 'bn' ? $au['expertise_bn'] : $au['expertise_en'])) ?></small><?php endif; ?>
                            </td>
                            <td data-label="Slug"><a href="<?= e(url('/authors/' . rawurlencode((string) $au['slug']))) ?>" target="_blank" rel="noopener noreferrer"><code>/authors/<?= e((string) $au['slug']) ?></code></a></td>
                            <td data-label="Action"><a href="<?= e(url('/admin/guides?edit_author=' . (int) $au['id'] . '#author-editor')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">Edit</a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
