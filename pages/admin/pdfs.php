<?php
declare(strict_types=1);

$lang = current_lang();

$q = trim((string) ($_GET['q'] ?? ''));
$filterFolder = !empty($_GET['folder_id']) ? (int) $_GET['folder_id'] : null;
$missing = (string) ($_GET['missing'] ?? '');
$sort = (string) ($_GET['sort'] ?? 'newest');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$returnUrl = $_SERVER['REQUEST_URI'] ?? '/admin/pdfs';

$where = [];
$params = [];
if ($q !== '') {
    $where[] = "(i.title_bn LIKE ? ESCAPE '\\\\' OR i.title_en LIKE ? ESCAPE '\\\\' OR i.author_en LIKE ? ESCAPE '\\\\' OR i.author_bn LIKE ? ESCAPE '\\\\' OR i.slug LIKE ? ESCAPE '\\\\')";
    $like = '%' . escape_like($q) . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($filterFolder !== null) {
    $where[] = 'i.folder_id = ?';
    $params[] = $filterFolder;
}
if ($missing === 'cover') {
    $where[] = "(i.cover IS NULL OR TRIM(i.cover) = '')";
} elseif ($missing === 'drive') {
    $where[] = "(i.drive_link IS NULL OR TRIM(i.drive_link) = '' OR i.drive_link LIKE 'local://%')";
} elseif ($missing === 'sample') {
    $where[] = "((i.sample_pages IS NULL OR i.sample_pages <= 0) AND (i.sample_drive_link IS NULL OR TRIM(i.sample_drive_link) = ''))";
} elseif ($missing === 'any') {
    $where[] = "(i.cover IS NULL OR TRIM(i.cover) = '' OR i.drive_link IS NULL OR TRIM(i.drive_link) = '' OR i.drive_link LIKE 'local://%' OR ((i.sample_pages IS NULL OR i.sample_pages <= 0) AND (i.sample_drive_link IS NULL OR TRIM(i.sample_drive_link) = '')))";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$orderSql = match ($sort) {
    'title'      => 'ORDER BY i.title_en ASC',
    'price_asc'  => 'ORDER BY i.is_free DESC, i.price ASC',
    'price_desc' => 'ORDER BY i.price DESC',
    default      => 'ORDER BY i.id DESC',
};

$totalCount = (int) (db_one("SELECT COUNT(*) AS c FROM items i $whereSql", $params)['c'] ?? 0);
$totalPages = max(1, (int) ceil($totalCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$pdfs = db_all(
    "SELECT i.id, i.folder_id, i.slug, i.title_bn, i.title_en, i.author_en, i.cover, i.price, i.is_free, i.sample_pages, i.sample_drive_link, i.total_pages, i.pages_status, i.is_featured, i.is_active, i.drive_link,
            f.name_en AS folder_en, f.name_bn AS folder_bn,
            (SELECT COUNT(*) FROM wishlist w WHERE w.item_id = i.id) AS wishlist_count
     FROM items i
     LEFT JOIN folders f ON f.id = i.folder_id
     $whereSql
     $orderSql
     LIMIT $perPage OFFSET $offset",
    $params
);

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$editPdf = $editId ? db_one('SELECT * FROM items WHERE id = ?', [$editId]) : null;
$folderOptions = build_folder_options();

$activeFiltersCount = ($q !== '' ? 1 : 0) + ($filterFolder !== null ? 1 : 0) + ($missing !== '' ? 1 : 0) + ($sort !== 'newest' ? 1 : 0);
$buildPageUrl = function (int $p) use ($q, $filterFolder, $missing, $sort): string {
    return url('/admin/pdfs?' . http_build_query([
        'q' => $q, 'folder_id' => $filterFolder ?? '', 'missing' => $missing, 'sort' => $sort, 'page' => $p,
    ]));
};
$filterQueryPrefix = http_build_query(array_filter([
    'q' => $q, 'folder_id' => $filterFolder ?? '', 'missing' => $missing, 'sort' => $sort !== 'newest' ? $sort : '', 'page' => $page > 1 ? $page : '',
], fn($v) => $v !== ''));
?>

<!-- Mobile Filter Trigger Bar -->
<div class="bx-mobile-only-bar">
    <button type="button" class="bx-btn bx-btn-outline" data-open-sheet="pdf-filter-sheet">
        <?= $lang === 'bn' ? 'ফিল্টার ও সার্চ' : 'Filters' ?>
        <?= $activeFiltersCount > 0 ? '(' . $activeFiltersCount . ')' : '' ?>
    </button>
    <a href="<?= e(url('/admin/pdfs#pdf-form')) ?>" class="bx-btn bx-btn-primary">+ <?= $lang === 'bn' ? 'নতুন PDF' : 'Add PDF' ?></a>
</div>

<!-- Filter Bar (Inline Desktop, Bottom Sheet on Mobile <=767px) -->
<div id="pdf-filter-sheet" class="bx-filter-sheet-wrap" style="margin-bottom:18px;">
    <section class="bx-card bx-filter-sheet-card">
        <div class="bx-sheet-head">
            <strong style="font-family:var(--font-serif);font-size:1.05rem;"><?= $lang === 'bn' ? 'ফিল্টার ও সার্চ' : 'Filter & Sort PDFs' ?></strong>
            <button type="button" class="bx-btn bx-btn-ghost" data-close-sheet aria-label="Close" style="min-width:44px;min-height:44px;">✕</button>
        </div>
        <form method="get" action="<?= e(url('/admin/pdfs')) ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <input type="search" name="q" class="bx-input" style="flex:1 1 180px;min-width:0;" value="<?= e($q) ?>" placeholder="<?= $lang === 'bn' ? 'শিরোনাম, লেখক বা স্লাগ...' : 'Search by title, author, slug...' ?>">
            <select name="folder_id" class="bx-input" style="flex:1 1 160px;min-width:0;">
                <option value=""><?= $lang === 'bn' ? 'সব ফোল্ডার' : 'All Folders' ?></option>
                <?php foreach ($folderOptions as $opt): ?>
                    <option value="<?= $opt['id'] ?>" <?= $filterFolder === $opt['id'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="missing" class="bx-input" style="flex:1 1 150px;min-width:130px;" aria-label="Missing info filter">
                <option value=""><?= $lang === 'bn' ? 'সব বই' : 'All PDFs' ?></option>
                <option value="any" <?= $missing === 'any' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'তথ্য অসম্পূর্ণ' : 'Missing Info' ?></option>
                <option value="cover" <?= $missing === 'cover' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'কভার নেই' : 'No Cover' ?></option>
                <option value="drive" <?= $missing === 'drive' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'সোর্স/Drive নেই' : 'No Drive Link' ?></option>
                <option value="sample" <?= $missing === 'sample' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'স্যাম্পল নেই' : 'No Sample' ?></option>
            </select>
            <select name="sort" class="bx-input" style="flex:1 1 140px;min-width:125px;" aria-label="Sort order">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'সর্বশেষ' : 'Newest' ?></option>
                <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'শিরোনাম (A-Z)' : 'Title (A-Z)' ?></option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'দাম: কম → বেশি' : 'Price: Low–High' ?></option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'দাম: বেশি → কম' : 'Price: High–Low' ?></option>
            </select>
            <button type="submit" class="bx-btn bx-btn-primary"><?= $lang === 'bn' ? 'প্রয়োগ করুন' : 'Apply' ?></button>
            <?php if ($activeFiltersCount > 0): ?>
                <a href="<?= e(url('/admin/pdfs')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'রিসেট' : 'Reset' ?></a>
            <?php endif; ?>
            <a href="<?= e(url('/admin/pdfs#pdf-form')) ?>" class="bx-btn bx-btn-outline">+ <?= $lang === 'bn' ? 'নতুন PDF' : 'Add PDF' ?></a>
            <a href="<?= e(url('/admin/import-csv')) ?>" class="bx-btn bx-btn-outline"><?= $lang === 'bn' ? 'CSV ইমপোর্ট' : 'CSV Import' ?></a>
            <a href="<?= e(url('/admin/prepare-queue')) ?>" class="bx-btn bx-btn-outline"><?= $lang === 'bn' ? 'প্রস্তুত কিউ' : 'Prepare Queue' ?></a>
        </form>
    </section>
</div>

<!-- Standalone Bulk Actions Form (HTML5 form="bx-bulk-pdf-form" prevents nested forms!) -->
<form method="post" action="<?= e(url('/admin/pdfs')) ?>" id="bx-bulk-pdf-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_pdfs">
    <input type="hidden" name="_return" value="<?= e($returnUrl) ?>">
</form>

<!-- PDF Table (Mobile Stacked Cards <=820px) + Quick Toggle, Duplicate & Bulk Actions -->
<section class="bx-card" style="margin-bottom:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <strong><?= $totalCount ?> <?= $lang === 'bn' ? 'টি PDF পাওয়া গেছে' : 'PDF item(s)' ?></strong>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;width:100%;max-width:740px;">
            <select name="bulk_action" form="bx-bulk-pdf-form" class="bx-input" style="flex:1 1 150px;min-width:0;">
                <option value=""><?= $lang === 'bn' ? 'বাল্ক অ্যাকশন...' : 'Bulk Action...' ?></option>
                <option value="activate"><?= $lang === 'bn' ? 'সক্রিয় করুন' : 'Activate Selected' ?></option>
                <option value="deactivate"><?= $lang === 'bn' ? 'নিষ্ক্রিয় করুন' : 'Deactivate Selected' ?></option>
                <option value="move"><?= $lang === 'bn' ? 'ফোল্ডারে সরান' : 'Move to Folder' ?></option>
                <option value="price_set"><?= $lang === 'bn' ? 'মূল্য নির্ধারণ করুন (৳)' : 'Set Price (৳)' ?></option>
                <option value="price_pct"><?= $lang === 'bn' ? 'শতাংশ ছাড় দিন (%)' : 'Apply % Discount' ?></option>
                <option value="price_sub"><?= $lang === 'bn' ? 'টাকা ছাড় দিন (-৳)' : 'Apply Fixed Discount (-৳)' ?></option>
                <option value="delete"><?= $lang === 'bn' ? 'মুছে ফেলুন' : 'Delete Selected' ?></option>
            </select>
            <input type="number" step="0.01" min="0" name="bulk_price_val" form="bx-bulk-pdf-form" class="bx-input" style="flex:0 1 120px;min-width:95px;" placeholder="<?= e($lang === 'bn' ? '৳ বা %' : 'Price / %') ?>">
            <select name="target_folder_id" form="bx-bulk-pdf-form" class="bx-input" style="flex:1 1 150px;min-width:0;" aria-label="Target folder">
                <option value=""><?= $lang === 'bn' ? 'টার্গেট ফোল্ডার' : 'Target Folder' ?></option>
                <?php foreach ($folderOptions as $opt): ?>
                    <option value="<?= $opt['id'] ?>"><?= e($opt['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" form="bx-bulk-pdf-form" class="bx-btn bx-btn-outline"><?= $lang === 'bn' ? 'প্রয়োগ' : 'Apply' ?></button>
        </div>
    </div>

    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards bx-pdfs-table">
            <thead>
                <tr>
                    <th style="width:32px;"><input type="checkbox" id="bx-bulk-select-all" aria-label="Select all"></th>
                    <th><?= $lang === 'bn' ? 'বই' : 'Book' ?></th>
                    <th><?= $lang === 'bn' ? 'ফোল্ডার' : 'Folder' ?></th>
                    <th><?= $lang === 'bn' ? 'মূল্য / নমুনা' : 'Price / Sample' ?></th>
                    <th><?= $lang === 'bn' ? 'ড্রাইভ / তথ্য' : 'Source / Info' ?></th>
                    <th><?= $lang === 'bn' ? 'অবস্থা' : 'Status' ?></th>
                    <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Actions' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$pdfs): ?>
                    <tr class="bx-empty-row"><td colspan="7" style="color:var(--text-muted);padding:24px;"><?= $lang === 'bn' ? 'কোনো PDF পাওয়া যায়নি।' : 'No matching PDFs found.' ?></td></tr>
                <?php else: ?>
                    <?php foreach ($pdfs as $p): ?>
                        <?php
                        $pid = (int) $p['id'];
                        $isDemo = str_starts_with((string) $p['drive_link'], 'local://');
                        $noCover = trim((string) $p['cover']) === '';
                        $noDrive = trim((string) $p['drive_link']) === '' || $isDemo;
                        $noSample = (int) $p['sample_pages'] <= 0 && trim((string) ($p['sample_drive_link'] ?? '')) === '';
                        $isActive = (int) $p['is_active'] === 1;
                        $editLink = url('/admin/pdfs?' . ($filterQueryPrefix !== '' ? $filterQueryPrefix . '&' : '') . 'edit=' . $pid . '#pdf-form');
                        ?>
                        <tr>
                            <td data-label="<?= e($lang === 'bn' ? 'নির্বাচন' : 'Select') ?>"><input type="checkbox" class="bx-bulk-cb" form="bx-bulk-pdf-form" name="ids[]" value="<?= $pid ?>" style="width:20px;height:20px;"></td>
                            <td class="bx-td-primary">
                                <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                                    <img src="<?= e(asset($p['cover'] ?: 'uploads/covers/cover-bangla.svg')) ?>" alt="" class="bx-thumb-preview" style="width:42px;height:56px;flex-shrink:0;">
                                    <div style="min-width:0;flex:1;">
                                        <strong style="display:block;overflow-wrap:anywhere;"><?= e($lang === 'bn' ? $p['title_bn'] : $p['title_en']) ?></strong>
                                        <span class="bx-ellipsis" style="font-size:0.8rem;color:var(--text-muted);"><?= e($p['title_en']) ?> • <?= e($p['slug']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="<?= e($lang === 'bn' ? 'ফোল্ডার' : 'Folder') ?>"><?= e($lang === 'bn' ? ($p['folder_bn'] ?: '—') : ($p['folder_en'] ?: '—')) ?></td>
                            <td data-label="<?= e($lang === 'bn' ? 'মূল্য' : 'Price') ?>">
                                <span>
                                    <?php if ((int) $p['is_free']): ?>
                                        <span class="bx-status-pill st-active"><?= $lang === 'bn' ? 'ফ্রি' : 'Free' ?></span>
                                    <?php else: ?>
                                        <strong>৳<?= number_format((float) $p['price'], 0) ?></strong>
                                    <?php endif; ?>
                                    <small style="color:var(--text-muted);margin-left:4px;">(<?= (int) $p['sample_pages'] ?>p)</small>
                                </span>
                            </td>
                            <td data-label="<?= e($lang === 'bn' ? 'তথ্য' : 'Source') ?>">
                                <?php $pgSt = (string) ($p['pages_status'] ?? 'unprocessed'); $procSt = (string) ($p['processing_status'] ?? 'idle'); ?>
                                <div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end;">
                                    <span class="bx-status-pill <?= $pgSt === 'ready' ? 'st-active' : 'st-pending' ?>">
                                        <?= $pgSt === 'ready' ? ($lang === 'bn' ? 'পৃষ্ঠা প্রস্তুত (' . (int) $p['total_pages'] . ')' : 'Pages Ready (' . (int) $p['total_pages'] . ')') : ($lang === 'bn' ? 'পৃষ্ঠা অপ্রস্তুত' : ucfirst($pgSt)) ?>
                                    </span>
                                    <?php if ($procSt === 'processing'): ?>
                                        <span class="bx-status-pill st-pending"><?= $lang === 'bn' ? 'পুনঃপ্রস্তুত চলছে' : 'Re-preparing...' ?></span>
                                    <?php elseif ($procSt === 'failed'): ?>
                                        <span class="bx-status-pill st-rejected"><?= $lang === 'bn' ? 'পুনঃপ্রস্তুতি ব্যর্থ' : 'Prep Failed' ?></span>
                                    <?php endif; ?>
                                    <span class="bx-badge"><?= $isDemo ? 'Local Demo' : 'Drive Protected' ?></span>
                                    <span class="bx-badge" title="Wishlist count">♥ <?= (int) ($p['wishlist_count'] ?? 0) ?></span>
                                    <?php if ($noCover): ?><span class="bx-status-pill st-pending"><?= $lang === 'bn' ? 'কভার নেই' : 'No Cover' ?></span><?php endif; ?>
                                    <?php if ($noDrive): ?><span class="bx-status-pill st-pending"><?= $lang === 'bn' ? 'Drive নেই' : 'No Drive' ?></span><?php endif; ?>
                                    <?php if ($noSample): ?><span class="bx-status-pill st-pending"><?= $lang === 'bn' ? 'স্যাম্পল নেই' : 'No Sample' ?></span><?php endif; ?>
                                </div>
                            </td>
                            <td data-label="<?= e($lang === 'bn' ? 'অবস্থা' : 'Status') ?>">
                                <form method="post" action="<?= e(url('/admin/pdfs')) ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_pdf">
                                    <input type="hidden" name="id" value="<?= $pid ?>">
                                    <input type="hidden" name="_return" value="<?= e($returnUrl) ?>">
                                    <button type="submit" class="bx-switch-btn <?= $isActive ? 'is-on' : '' ?>" aria-pressed="<?= $isActive ? 'true' : 'false' ?>" title="<?= e($lang === 'bn' ? 'অবস্থা পরিবর্তন করুন' : 'Quick toggle active') ?>">
                                        <span class="bx-switch-dot"></span>
                                        <span><?= $isActive ? ($lang === 'bn' ? 'সক্রিয়' : 'Active') : ($lang === 'bn' ? 'লুকানো' : 'Hidden') ?></span>
                                    </button>
                                </form>
                            </td>
                            <td class="bx-td-actions">
                                <div class="bx-row-icon-actions">
                                    <a href="<?= e(url('/admin/prepare-pages?id=' . $pid)) ?>" class="bx-btn bx-btn-primary bx-icon-btn" title="<?= e($lang === 'bn' ? 'পৃষ্ঠা প্রস্তুত করুন' : 'Prepare Pages') ?>" aria-label="<?= e($lang === 'bn' ? 'পৃষ্ঠা প্রস্তুত করুন' : 'Prepare Pages') ?>">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                                    </a>
                                    <a href="<?= e($editLink) ?>" class="bx-btn bx-btn-outline bx-icon-btn" title="<?= e($lang === 'bn' ? 'সম্পাদনা করুন' : 'Edit Book') ?>" aria-label="<?= e($lang === 'bn' ? 'সম্পাদনা করুন' : 'Edit Book') ?>">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                    </a>
                                    <form method="post" action="<?= e(url('/admin/pdfs')) ?>" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="duplicate_pdf">
                                        <input type="hidden" name="id" value="<?= $pid ?>">
                                        <input type="hidden" name="_return" value="<?= e($returnUrl) ?>">
                                        <button type="submit" class="bx-btn bx-btn-ghost bx-icon-btn" title="<?= e($lang === 'bn' ? 'কপি তৈরি করুন' : 'Duplicate Book') ?>" aria-label="<?= e($lang === 'bn' ? 'কপি তৈরি করুন' : 'Duplicate Book') ?>">
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="bx-pagination">
            <?php if ($page > 1): ?>
                <a href="<?= e($buildPageUrl($page - 1)) ?>" class="bx-btn bx-btn-outline">← <?= $lang === 'bn' ? 'পূর্ববর্তী' : 'Prev' ?></a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <span style="font-size:0.86rem;font-weight:700;color:var(--text-muted);">
                <?= $lang === 'bn' ? 'পৃষ্ঠা ' . $page . ' / ' . $totalPages : "Page $page / $totalPages" ?>
            </span>
            <?php if ($page < $totalPages): ?>
                <a href="<?= e($buildPageUrl($page + 1)) ?>" class="bx-btn bx-btn-outline"><?= $lang === 'bn' ? 'পরবর্তী' : 'Next' ?> →</a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Add / Edit PDF Form + Live 3D Book Preview -->
<section class="bx-card" id="pdf-form">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
        <h2 style="margin:0;font-size:1.15rem;font-family:var(--font-serif);">
            <?= $editPdf ? ($lang === 'bn' ? 'PDF সম্পাদনা করুন' : 'Edit PDF Item') : ($lang === 'bn' ? 'নতুন PDF যুক্ত করুন' : 'Add New PDF Item') ?>
        </h2>
        <?php if ($editPdf): ?>
            <div style="display:flex;gap:8px;">
                <form method="post" action="<?= e(url('/admin/pdfs')) ?>" data-confirm-msg="<?= e($lang === 'bn' ? 'এই PDF-টি স্থায়ীভাবে মুছে ফেলবেন?' : 'Permanently delete this PDF item?') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_pdf">
                    <input type="hidden" name="id" value="<?= (int) $editPdf['id'] ?>">
                    <input type="hidden" name="_return" value="<?= e($returnUrl) ?>">
                    <button type="submit" class="bx-btn bx-btn-ghost" style="color:var(--rose-text);"><?= $lang === 'bn' ? 'মুছুন' : 'Delete' ?></button>
                </form>
                <a href="<?= e(url('/admin/pdfs' . ($filterQueryPrefix !== '' ? '?' . $filterQueryPrefix : ''))) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'নতুন তৈরি' : 'New PDF' ?></a>
            </div>
        <?php endif; ?>
    </div>

    <div class="bx-grid-2" style="align-items:start;">
        <form method="post" action="<?= e(url('/admin/pdfs')) ?>" enctype="multipart/form-data" class="bx-track-dirty">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_pdf">
            <input type="hidden" name="_return" value="<?= e($returnUrl) ?>">
            <?php if ($editPdf): ?>
                <input type="hidden" name="id" value="<?= (int) $editPdf['id'] ?>">
            <?php endif; ?>

            <div class="bx-grid-2" style="gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ফোল্ডার' : 'Folder' ?></label>
                    <select name="folder_id" class="bx-input">
                        <option value=""><?= $lang === 'bn' ? '— ফোল্ডার নেই —' : '— Unassigned —' ?></option>
                        <?php foreach ($folderOptions as $opt): ?>
                            <option value="<?= $opt['id'] ?>" <?= (int) ($editPdf['folder_id'] ?? 0) === $opt['id'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'স্লাগ' : 'URL Slug' ?></label>
                    <input type="text" name="slug" class="bx-input" data-slug-target value="<?= e((string) ($editPdf['slug'] ?? '')) ?>" placeholder="auto-from-english-title">
                </div>
            </div>

            <div class="bx-grid-2" style="gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'শিরোনাম (বাংলা) *' : 'Title (Bangla) *' ?></label>
                    <input type="text" id="pdf-title-bn" name="title_bn" class="bx-input" required value="<?= e((string) ($editPdf['title_bn'] ?? '')) ?>">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'শিরোনাম (ইংরেজি) *' : 'Title (English) *' ?></label>
                    <input type="text" id="pdf-title-en" name="title_en" class="bx-input" required data-slug-source value="<?= e((string) ($editPdf['title_en'] ?? '')) ?>">
                </div>
            </div>

            <div class="bx-grid-2" style="gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'লেখক (বাংলা)' : 'Author (Bangla)' ?></label>
                    <input type="text" name="author_bn" class="bx-input" value="<?= e((string) ($editPdf['author_bn'] ?? '')) ?>">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'লেখক (ইংরেজি)' : 'Author (English)' ?></label>
                    <input type="text" id="pdf-author-en" name="author_en" class="bx-input" value="<?= e((string) ($editPdf['author_en'] ?? '')) ?>">
                </div>
            </div>

            <div class="bx-grid-2" style="gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">ISBN (Optional)</label>
                    <input type="text" name="isbn" class="bx-input" maxlength="40" value="<?= e((string) ($editPdf['isbn'] ?? '')) ?>" placeholder="978-...">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'প্রকাশক (Publisher)' : 'Publisher (Optional)' ?></label>
                    <input type="text" name="publisher" class="bx-input" maxlength="160" value="<?= e((string) ($editPdf['publisher'] ?? '')) ?>">
                </div>
            </div>

            <div class="bx-grid-3" style="gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'সংস্করণ (Edition)' : 'Edition' ?></label>
                    <input type="text" name="edition" class="bx-input" maxlength="80" value="<?= e((string) ($editPdf['edition'] ?? '')) ?>" placeholder="2026 Revised">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'বইয়ের ভাষা' : 'Book Language' ?></label>
                    <input type="text" name="book_language" class="bx-input" maxlength="60" value="<?= e((string) ($editPdf['book_language'] ?? '')) ?>" placeholder="Bangla / English">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ট্যাগ (কমা দিয়ে)' : 'Tags (comma separated)' ?></label>
                    <input type="text" name="tags" class="bx-input" maxlength="255" value="<?= e((string) ($editPdf['tags'] ?? '')) ?>" placeholder="bcs, grammar, guide">
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">
                    <?= $lang === 'bn' ? 'Google Drive লিংক (ঐচ্ছিক — কম্পিউটার থেকে পৃষ্ঠা প্রস্তুত করলে খালি রাখা যাবে)' : 'Google Drive URL (Optional — hidden server-side)' ?>
                </label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <input type="text" inputmode="url" id="pdf-drive-link" name="drive_link" class="bx-input" style="flex:1 1 220px;min-width:0;" value="<?= e((string) ($editPdf['drive_link'] ?? '')) ?>" placeholder="https://drive.google.com/file/d/FILE_ID/view?usp=sharing (optional)">
                    <button type="button" id="btn-test-drive" class="bx-btn bx-btn-outline" data-endpoint="<?= e(url('/admin/api/test-drive-link')) ?>">
                        <?= $lang === 'bn' ? 'লিংক পরীক্ষা' : 'Test Link' ?>
                    </button>
                </div>
                <small id="drive-test-result" style="display:block;margin-top:6px;font-weight:600;line-height:1.45;"></small>
            </div>

            <div class="bx-grid-3" style="gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'মূল্য (৳)' : 'Price (৳)' ?></label>
                    <input type="number" step="0.01" inputmode="decimal" id="pdf-price" name="price" class="bx-input" value="<?= e((string) ($editPdf['price'] ?? '150')) ?>">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'পূর্বের মূল্য' : 'Original Price' ?></label>
                    <input type="number" step="0.01" inputmode="decimal" name="original_price" class="bx-input" value="<?= e((string) ($editPdf['original_price'] ?? '')) ?>">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ফ্রি নমুনা পৃষ্ঠা' : 'Free Sample Pages' ?></label>
                    <input type="number" min="0" inputmode="numeric" name="sample_pages" class="bx-input" value="<?= e((string) ($editPdf['sample_pages'] ?? '4')) ?>">
                </div>
            </div>

            <div class="bx-grid-2" style="gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'বিবরণ (বাংলা)' : 'Description (Bangla)' ?></label>
                    <textarea name="description_bn" class="bx-input" rows="2"><?= e((string) ($editPdf['description_bn'] ?? '')) ?></textarea>
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'বিবরণ (ইংরেজি)' : 'Description (English)' ?></label>
                    <textarea name="description_en" class="bx-input" rows="2"><?= e((string) ($editPdf['description_en'] ?? '')) ?></textarea>
                </div>
            </div>

            <details style="margin-bottom:14px;padding:12px;border:1px solid var(--border);border-radius:10px;background:var(--bg-alt);">
                <summary style="cursor:pointer;font-weight:700;font-size:0.9rem;"><?= $lang === 'bn' ? 'স্ট্রাকচার্ড বইয়ের তথ্য (সংক্ষিপ্ত উত্তর, কী শিখবেন, কাদের জন্য, FAQ)' : 'Structured Book Details (Short Answer, Learnings, Audience & FAQs)' ?></summary>
                <div class="bx-grid-2" style="gap:10px;margin-top:10px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;"><?= $lang === 'bn' ? 'সংক্ষিপ্ত উত্তর (বাংলা, ৪০–৬০ শব্দ)' : 'Short Direct Answer (BN, ~40-60 words)' ?></label>
                        <textarea name="short_answer_bn" class="bx-input" rows="2" placeholder="বইটির মূল বিষয়বস্তু ও উপকারিতা ২-৩ বাক্যে"><?= e((string) ($editPdf['short_answer_bn'] ?? '')) ?></textarea>
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">Short Direct Answer (EN, ~40-60 words)</label>
                        <textarea name="short_answer_en" class="bx-input" rows="2" placeholder="Concise 40-60 word direct summary shown below H1"><?= e((string) ($editPdf['short_answer_en'] ?? '')) ?></textarea>
                    </div>
                </div>
                <div class="bx-grid-2" style="gap:10px;margin-top:10px;margin-bottom:10px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">What You Will Learn (BN, 1 per line)</label>
                        <textarea name="what_you_learn_bn" class="bx-input" rows="3"><?= e((string) ($editPdf['what_you_learn_bn'] ?? '')) ?></textarea>
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">What You Will Learn (EN, 1 per line)</label>
                        <textarea name="what_you_learn_en" class="bx-input" rows="3"><?= e((string) ($editPdf['what_you_learn_en'] ?? '')) ?></textarea>
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">Who It Is For (BN)</label>
                        <textarea name="who_for_bn" class="bx-input" rows="2"><?= e((string) ($editPdf['who_for_bn'] ?? '')) ?></textarea>
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">Who It Is For (EN)</label>
                        <textarea name="who_for_en" class="bx-input" rows="2"><?= e((string) ($editPdf['who_for_en'] ?? '')) ?></textarea>
                    </div>
                </div>
                <?php $bookFaqs = $editPdf ? content_parse_faqs_json($editPdf['faqs_json'] ?? null) : []; ?>
                <div data-faq-wrap>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <strong style="font-size:0.84rem;">Book FAQs</strong>
                        <button type="button" class="bx-btn bx-btn-outline bx-btn-sm" data-faq-add>+ Add FAQ</button>
                    </div>
                    <div data-faq-list>
                        <?php foreach ($bookFaqs as $fq): ?>
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
            </details>

            <details style="margin-bottom:14px;padding:12px;border:1px solid var(--border);border-radius:10px;background:var(--bg-alt);">
                <summary style="cursor:pointer;font-weight:700;font-size:0.9rem;"><?= $lang === 'bn' ? 'SEO মেটাডাটা (ঐচ্ছিক — স্বয়ংক্রিয় ফলব্যাক আছে)' : 'SEO Metadata (Optional — auto-fallback if blank)' ?></summary>
                <div class="bx-grid-2" style="gap:10px;margin-top:10px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">SEO Title (Bangla)</label>
                        <input type="text" name="seo_title_bn" class="bx-input" maxlength="220" value="<?= e((string) ($editPdf['seo_title_bn'] ?? '')) ?>" placeholder="Auto from Bangla title">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">SEO Title (English)</label>
                        <input type="text" name="seo_title_en" class="bx-input" maxlength="220" value="<?= e((string) ($editPdf['seo_title_en'] ?? '')) ?>" placeholder="Auto from English title">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">SEO Description (Bangla)</label>
                        <textarea name="seo_description_bn" class="bx-input" rows="2" maxlength="320"><?= e((string) ($editPdf['seo_description_bn'] ?? '')) ?></textarea>
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">SEO Description (English)</label>
                        <textarea name="seo_description_en" class="bx-input" rows="2" maxlength="320"><?= e((string) ($editPdf['seo_description_en'] ?? '')) ?></textarea>
                    </div>
                </div>
            </details>

            <div class="bx-grid-2" style="gap:12px;margin-bottom:14px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'কভার ছবি (ক্যামেরা/গ্যালারি)' : 'Cover Image (Camera / Gallery)' ?></label>
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="bx-input" data-preview-target="pv-pdf-cover">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'মোট পৃষ্ঠা' : 'Total Pages' ?></label>
                    <input type="number" min="1" inputmode="numeric" name="total_pages" class="bx-input" value="<?= e((string) ($editPdf['total_pages'] ?? '')) ?>">
                </div>
            </div>

            <div class="bx-grid-2" style="gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'প্রকাশের সময় (Publish At)' : 'Publish At (Optional)' ?></label>
                    <input type="datetime-local" name="publish_at" class="bx-input" value="<?= e(!empty($editPdf['publish_at']) ? date('Y-m-d\TH:i', strtotime((string) $editPdf['publish_at'])) : '') ?>">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'অপ্রকাশের সময় (Unpublish At)' : 'Unpublish At (Optional)' ?></label>
                    <input type="datetime-local" name="unpublish_at" class="bx-input" value="<?= e(!empty($editPdf['unpublish_at']) ? date('Y-m-d\TH:i', strtotime((string) $editPdf['unpublish_at'])) : '') ?>">
                </div>
            </div>

            <div style="display:flex;gap:18px;flex-wrap:wrap;margin-bottom:10px;">
                <label style="display:flex;align-items:center;gap:6px;min-height:44px;font-weight:600;">
                    <input type="checkbox" id="pdf-is-free" name="is_free" value="1" <?= !empty($editPdf['is_free']) ? 'checked' : '' ?>>
                    <?= $lang === 'bn' ? 'ফ্রি বই' : 'Free PDF' ?>
                </label>
                <label style="display:flex;align-items:center;gap:6px;min-height:44px;font-weight:600;">
                    <input type="checkbox" name="is_featured" value="1" <?= !empty($editPdf['is_featured']) ? 'checked' : '' ?>>
                    <?= $lang === 'bn' ? 'ফিচার্ড' : 'Featured' ?>
                </label>
                <label style="display:flex;align-items:center;gap:6px;min-height:44px;font-weight:600;">
                    <input type="checkbox" name="is_active" value="1" <?= !$editPdf || (int) $editPdf['is_active'] ? 'checked' : '' ?>>
                    <?= $lang === 'bn' ? 'সক্রিয়' : 'Active' ?>
                </label>
            </div>

            <div class="bx-sticky-form-actions">
                <button type="submit" class="bx-btn bx-btn-primary">
                    <?= $editPdf ? ($lang === 'bn' ? 'PDF আপডেট করুন' : 'Update PDF') : ($lang === 'bn' ? 'PDF যুক্ত করুন' : 'Save PDF Item') ?>
                </button>
                <?php if ($editPdf): ?>
                    <a href="<?= e(url('/admin/pdfs')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'বাতিল' : 'Cancel' ?></a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Live 3D Book Preview Card -->
        <aside class="bx-card" style="background:var(--bg);max-width:340px;width:100%;margin:0 auto;">
            <div style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);margin-bottom:12px;font-weight:700;">
                <?= $lang === 'bn' ? 'লাইভ ইউজার প্রিভিউ' : 'Live 3D Card Preview' ?>
            </div>
            <div class="bx-book-3d">
                <div class="bx-book-card">
                    <div class="bx-book-cover">
                        <img id="pv-pdf-cover" src="<?= e(asset((string) ($editPdf['cover'] ?? 'uploads/covers/cover-bangla.svg'))) ?>" alt="Cover Preview">
                        <span id="pv-pdf-ribbon" class="bx-book-ribbon <?= !empty($editPdf['is_free']) ? 'free' : '' ?>">
                            <?= !empty($editPdf['is_free']) ? 'Free' : '৳' . number_format((float) ($editPdf['price'] ?? 150), 0) ?>
                        </span>
                    </div>
                    <div class="bx-book-info">
                        <h3 id="pv-pdf-title" class="bx-book-title" style="margin:0 0 4px;">
                            <?= e((string) ($editPdf['title_bn'] ?? ($editPdf['title_en'] ?? 'Sample PDF Title'))) ?>
                        </h3>
                        <p id="pv-pdf-author" class="bx-book-author" style="margin:0 0 8px;">
                            <?= e((string) ($editPdf['author_en'] ?? 'Bookexa Author')) ?>
                        </p>
                        <strong id="pv-pdf-price" style="color:var(--primary);">
                            <?= !empty($editPdf['is_free']) ? 'Free' : '৳' . number_format((float) ($editPdf['price'] ?? 150), 0) ?>
                        </strong>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</section>
