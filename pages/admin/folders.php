<?php
declare(strict_types=1);

$lang = current_lang();

$allFolders = db_all(
    "SELECT f.*, COUNT(i.id) AS direct_items
     FROM folders f
     LEFT JOIN items i ON i.folder_id = f.id
     GROUP BY f.id
     ORDER BY f.sort_order ASC, f.id ASC"
);

$byParent = [];
$byId = [];
foreach ($allFolders as $f) {
    $byId[(int) $f['id']] = $f;
    $pid = $f['parent_id'] !== null ? (int) $f['parent_id'] : 0;
    $byParent[$pid][] = $f;
}

$subtreeCounts = [];
$computeSubtree = function (int $fid) use (&$computeSubtree, &$subtreeCounts, $byParent, $byId): int {
    if (isset($subtreeCounts[$fid])) {
        return $subtreeCounts[$fid];
    }
    $total = (int) ($byId[$fid]['direct_items'] ?? 0);
    foreach ($byParent[$fid] ?? [] as $child) {
        $total += $computeSubtree((int) $child['id']);
    }
    return $subtreeCounts[$fid] = $total;
};
foreach ($allFolders as $f) {
    $computeSubtree((int) $f['id']);
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$editFolder = $editId ? ($byId[$editId] ?? null) : null;
$prefillParent = isset($_GET['add_parent']) ? (int) $_GET['add_parent'] : null;

$parentOptions = build_folder_options(null, 0, $editFolder ? (int) $editFolder['id'] : null);
$allFolderOptions = build_folder_options();

$returnUrl = $_SERVER['REQUEST_URI'] ?? '/admin/folders';

$renderTree = function (int $parentId, int $depth = 0) use (&$renderTree, $byParent, $subtreeCounts, $lang, $returnUrl): void {
    $nodes = $byParent[$parentId] ?? [];
    if (!$nodes) {
        return;
    }
    echo '<ul class="' . ($parentId === 0 ? 'bx-tree' : 'bx-tree-children') . '">';
    foreach ($nodes as $f) {
        $fid = (int) $f['id'];
        $hasChildren = !empty($byParent[$fid]);
        $thumbSrc = asset((string) ($f['thumbnail'] ?: 'uploads/folders/academic.svg'));
        $direct = (int) $f['direct_items'];
        $total = $subtreeCounts[$fid] ?? $direct;
        $crumb = folder_breadcrumb_string($fid);
        $isActive = (int) $f['is_active'] === 1;
        $slugTitle = '/' . $f['slug'] . ' — ' . $crumb;

        echo '<li class="bx-tree-node" data-node-id="' . $fid . '" style="--tree-depth:' . $depth . ';">';
        echo '<div class="bx-tree-row" draggable="true" data-folder-id="' . $fid . '">';
        echo '<div class="bx-tree-left">';
        if ($hasChildren) {
            echo '<button type="button" class="bx-tree-toggle" data-tree-toggle aria-expanded="true" aria-label="Toggle children">›</button>';
        } else {
            echo '<span class="bx-tree-spacer" aria-hidden="true"></span>';
        }
        echo '<span class="bx-drag-handle" title="' . ($lang === 'bn' ? 'টেনে সরান' : 'Drag handle') . '">⋮⋮</span>';
        echo '<img src="' . e($thumbSrc) . '" alt="" class="bx-tree-thumb">';
        echo '<div class="bx-tree-name-col" title="' . e($slugTitle) . '">';
        echo '<strong class="bx-tree-bn bx-inline-edit" data-inline-rename="' . $fid . '" data-endpoint="' . e(url('/admin/api/folder-rename')) . '">' . e($f['name_bn']) . '</strong>';
        echo '<span class="bx-tree-en">' . e($f['name_en']) . ' · /' . e($f['slug']) . '</span>';
        echo '</div>';
        echo '</div>';

        echo '<span class="bx-tree-count" title="' . e($direct . ' direct / ' . $total . ' total') . '">' . $direct . ' / ' . $total . '</span>';

        echo '<div class="bx-tree-actions">';
        echo '<form method="post" action="' . e(url('/admin/folders')) . '" style="margin:0;">'
            . csrf_field()
            . '<input type="hidden" name="action" value="toggle_folder">'
            . '<input type="hidden" name="id" value="' . $fid . '">'
            . '<input type="hidden" name="_return" value="' . e($returnUrl) . '">'
            . '<button type="submit" class="bx-switch-btn ' . ($isActive ? 'is-on' : '') . '" aria-pressed="' . ($isActive ? 'true' : 'false') . '" title="' . ($lang === 'bn' ? 'অবস্থা পরিবর্তন' : 'Toggle status') . '">'
            . '<span class="bx-switch-dot"></span><span>' . ($isActive ? ($lang === 'bn' ? 'সক্রিয়' : 'Active') : ($lang === 'bn' ? 'লুকানো' : 'Hidden')) . '</span>'
            . '</button></form>';

        echo '<a href="' . e(url('/admin/folders?add_parent=' . $fid . '#folder-form-sheet')) . '" class="bx-btn bx-btn-ghost" style="min-height:40px;padding:6px 10px;font-size:0.8rem;" title="' . e($lang === 'bn' ? 'এখানে সাব-ফোল্ডার যোগ করুন' : 'Add subfolder here') . '">+ ' . ($lang === 'bn' ? 'সাব' : 'Sub') . '</a>';

        echo '<div class="bx-kebab-wrap">';
        echo '<button type="button" class="bx-kebab-btn" data-kebab-toggle aria-label="Folder Actions" style="min-height:40px;">⋮</button>';
        echo '<div class="bx-kebab-menu">';
        echo '<a href="' . e(url('/admin/folders?edit=' . $fid . '#folder-form-sheet')) . '" class="bx-btn bx-btn-ghost">' . ($lang === 'bn' ? 'সম্পাদনা' : 'Edit') . '</a>';
        echo '<a href="' . e(url('/admin/folders?add_parent=' . $fid . '#folder-form-sheet')) . '" class="bx-btn bx-btn-ghost">+ ' . ($lang === 'bn' ? 'সাব-ফোল্ডার যোগ' : 'Add Subfolder Here') . '</a>';
        echo '<button type="button" class="bx-btn bx-btn-ghost" data-folder-step="' . $fid . '" data-dir="up" data-endpoint="' . e(url('/admin/api/folder-step')) . '">↑ ' . ($lang === 'bn' ? 'ওপরে নিন' : 'Move Up') . '</button>';
        echo '<button type="button" class="bx-btn bx-btn-ghost" data-folder-step="' . $fid . '" data-dir="down" data-endpoint="' . e(url('/admin/api/folder-step')) . '">↓ ' . ($lang === 'bn' ? 'নিচে নিন' : 'Move Down') . '</button>';
        echo '<button type="button" class="bx-btn bx-btn-ghost" data-move-modal-open="' . $fid . '" data-folder-name="' . e($f['name_en']) . '">⇄ ' . ($lang === 'bn' ? 'স্থানান্তর...' : 'Move To...') . '</button>';

        $confirmDel = $lang === 'bn'
            ? 'এই ফোল্ডারটি মুছে ফেলবেন? এর সাব-ফোল্ডারগুলোও মুছে যেতে পারে!'
            : 'Delete this folder and its nested subfolders? PDFs inside will become unassigned.';
        echo '<form method="post" action="' . e(url('/admin/folders')) . '" data-confirm-msg="' . e($confirmDel) . '">' . csrf_field() . '<input type="hidden" name="action" value="delete_folder"><input type="hidden" name="id" value="' . $fid . '"><input type="hidden" name="_return" value="' . e($returnUrl) . '"><button type="submit" class="bx-btn bx-btn-ghost" style="color:var(--rose-text);">✕ ' . ($lang === 'bn' ? 'মুছুন' : 'Delete') . '</button></form>';
        echo '</div></div></div>';
        echo '</div>';

        if ($hasChildren) {
            $renderTree($fid, $depth + 1);
        }
        echo '</li>';
    }
    echo '</ul>';
};
?>

<!-- Compact Full-Width Folder Tree Card -->
<section class="bx-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <div>
            <h2 style="margin:0;font-size:1.12rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'ফোল্ডার ট্রি' : 'Folder Hierarchy Tree' ?></h2>
            <small style="color:var(--text-muted);"><?= $lang === 'bn' ? 'টেনে বা ⋮ মেনু থেকে সাজান ও সাব-ফোল্ডার যোগ করুন।' : 'Drag handle or open ⋮ menu to edit, add subfolder here, or reorder.' ?></small>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" id="btn-expand-all" class="bx-btn bx-btn-ghost" style="padding:8px 12px;font-size:0.84rem;"><?= $lang === 'bn' ? 'সব খুলুন' : 'Expand All' ?></button>
            <button type="button" id="btn-collapse-all" class="bx-btn bx-btn-ghost" style="padding:8px 12px;font-size:0.84rem;"><?= $lang === 'bn' ? 'সব গুটিয়ে নিন' : 'Collapse All' ?></button>
            <button type="button" class="bx-btn bx-btn-primary" data-open-sheet="folder-form-sheet">+ <?= $lang === 'bn' ? 'নতুন ফোল্ডার' : 'Create Folder' ?></button>
        </div>
    </div>

    <div class="bx-tree-scroll" id="bx-folder-tree-root" data-move-endpoint="<?= e(url('/admin/api/folder-move')) ?>">
        <?php if (empty($byParent[0])): ?>
            <p style="color:var(--text-muted);"><?= $lang === 'bn' ? 'এখনও কোনো ফোল্ডার তৈরি হয়নি।' : 'No folders created yet.' ?></p>
        <?php else: ?>
            <?php $renderTree(0); ?>
        <?php endif; ?>
    </div>
</section>

<!-- Side Drawer (Desktop) / Bottom Sheet (Mobile) for Add / Edit Folder -->
<div id="folder-form-sheet" class="bx-folder-sheet-wrap<?= ($editFolder || $prefillParent) ? ' open' : '' ?>">
    <section class="bx-card bx-folder-sheet-card" id="folder-form">
        <div class="bx-sheet-head">
            <strong style="font-family:var(--font-serif);font-size:1.08rem;">
                <?= $editFolder ? ($lang === 'bn' ? 'ফোল্ডার সম্পাদনা' : 'Edit Folder') : ($lang === 'bn' ? 'নতুন ফোল্ডার তৈরি' : 'Create New Folder') ?>
            </strong>
            <div style="display:flex;gap:6px;align-items:center;">
                <?php if ($editFolder || $prefillParent): ?>
                    <a href="<?= e(url('/admin/folders')) ?>" class="bx-btn bx-btn-ghost" style="padding:8px 12px;font-size:0.84rem;"><?= $lang === 'bn' ? 'নতুন ফর্ম' : 'Reset' ?></a>
                <?php endif; ?>
                <button type="button" class="bx-btn bx-btn-ghost" data-close-sheet aria-label="Close" style="min-width:44px;min-height:44px;">✕</button>
            </div>
        </div>

        <?php if ($editFolder): ?>
            <div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:12px;">
                <?= $lang === 'bn' ? 'বর্তমান পথ:' : 'Breadcrumb:' ?> <strong><?= e(folder_breadcrumb_string((int) $editFolder['id'])) ?></strong>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/admin/folders')) ?>" enctype="multipart/form-data" class="bx-track-dirty">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_folder">
            <input type="hidden" name="_return" value="<?= e($returnUrl) ?>">
                <?php if ($editFolder): ?>
                    <input type="hidden" name="id" value="<?= (int) $editFolder['id'] ?>">
                <?php endif; ?>

                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'প্যারেন্ট ফোল্ডার' : 'Parent Folder' ?></label>
                    <?php $selParent = $editFolder ? $editFolder['parent_id'] : $prefillParent; ?>
                    <select name="parent_id" class="bx-input">
                        <option value=""><?= $lang === 'bn' ? 'মূল স্তর (Root Level)' : 'Root (Top Level)' ?></option>
                        <?php foreach ($parentOptions as $opt): ?>
                            <option value="<?= $opt['id'] ?>" <?= (int) $selParent === $opt['id'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="bx-grid-2" style="gap:12px;margin-bottom:12px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'বাংলা নাম *' : 'Name (Bangla) *' ?></label>
                        <input type="text" name="name_bn" class="bx-input" required value="<?= e((string) ($editFolder['name_bn'] ?? '')) ?>" placeholder="একাডেমিক বই">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ইংরেজি নাম *' : 'Name (English) *' ?></label>
                        <input type="text" name="name_en" class="bx-input" required data-slug-source value="<?= e((string) ($editFolder['name_en'] ?? '')) ?>" placeholder="Academic Books">
                    </div>
                </div>

                <div class="bx-grid-3" style="gap:12px;margin-bottom:12px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'স্লাগ (URL)' : 'Slug' ?></label>
                        <input type="text" name="slug" class="bx-input" data-slug-target value="<?= e((string) ($editFolder['slug'] ?? '')) ?>" placeholder="auto-generated">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ক্রম (Sort)' : 'Sort Order' ?></label>
                        <input type="number" name="sort_order" class="bx-input" value="<?= e((string) ($editFolder['sort_order'] ?? 0)) ?>">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'থিম রঙ' : 'Accent Color' ?></label>
                        <input type="color" name="accent_color" class="bx-input" style="padding:4px;height:44px;" value="<?= e((string) ($editFolder['accent_color'] ?? '#232766')) ?>">
                    </div>
                </div>

                <div class="bx-grid-2" style="gap:12px;margin-bottom:12px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'বিবরণ (বাংলা)' : 'Description (Bangla)' ?></label>
                        <textarea name="description_bn" class="bx-input" rows="2"><?= e((string) ($editFolder['description_bn'] ?? '')) ?></textarea>
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'বিবরণ (ইংরেজি)' : 'Description (English)' ?></label>
                        <textarea name="description_en" class="bx-input" rows="2"><?= e((string) ($editFolder['description_en'] ?? '')) ?></textarea>
                    </div>
                </div>

                <details style="margin-bottom:14px;padding:12px;border:1px solid var(--border);border-radius:10px;background:var(--bg-alt);">
                    <summary style="cursor:pointer;font-weight:700;font-size:0.9rem;"><?= $lang === 'bn' ? 'SEO মেটাডাটা (ঐচ্ছিক — স্বয়ংক্রিয় ফলব্যাক আছে)' : 'SEO Metadata (Optional — auto-fallback if blank)' ?></summary>
                    <div class="bx-grid-2" style="gap:10px;margin-top:10px;">
                        <div>
                            <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">SEO Title (Bangla)</label>
                            <input type="text" name="seo_title_bn" class="bx-input" maxlength="220" value="<?= e((string) ($editFolder['seo_title_bn'] ?? '')) ?>" placeholder="<?= $lang === 'bn' ? 'খালি রাখলে নাম ব্যবহার হবে' : 'Auto from Bangla name' ?>">
                        </div>
                        <div>
                            <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">SEO Title (English)</label>
                            <input type="text" name="seo_title_en" class="bx-input" maxlength="220" value="<?= e((string) ($editFolder['seo_title_en'] ?? '')) ?>" placeholder="Auto from English name">
                        </div>
                        <div>
                            <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">SEO Description (Bangla)</label>
                            <textarea name="seo_description_bn" class="bx-input" rows="2" maxlength="320"><?= e((string) ($editFolder['seo_description_bn'] ?? '')) ?></textarea>
                        </div>
                        <div>
                            <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.84rem;">SEO Description (English)</label>
                            <textarea name="seo_description_en" class="bx-input" rows="2" maxlength="320"><?= e((string) ($editFolder['seo_description_en'] ?? '')) ?></textarea>
                        </div>
                    </div>
                </details>

                <div style="margin-bottom:14px;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'থাম্বনেইল ছবি (ক্যামেরা/গ্যালারি)' : 'Thumbnail Image (Camera / Gallery)' ?></label>
                    <input type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp" class="bx-input" data-preview-target="folder-thumb-preview" data-crop-hidden="folder-cropped-data">
                    <input type="hidden" name="cropped_thumbnail" id="folder-cropped-data">
                    <?php $prevSrc = !empty($editFolder['thumbnail']) ? asset($editFolder['thumbnail']) : ''; ?>
                    <img id="folder-thumb-preview" src="<?= e($prevSrc) ?>" alt="" class="bx-live-preview" style="<?= $prevSrc ? '' : 'display:none;' ?>">
                </div>

                <div class="bx-grid-2" style="gap:12px;margin-bottom:12px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'প্রকাশের সময় (ঐচ্ছিক)' : 'Publish At (Optional)' ?></label>
                        <input type="datetime-local" name="publish_at" class="bx-input" value="<?= e(!empty($editFolder['publish_at']) ? date('Y-m-d\TH:i', strtotime((string) $editFolder['publish_at'])) : '') ?>">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'অপ্রকাশের সময় (ঐচ্ছিক)' : 'Unpublish At (Optional)' ?></label>
                        <input type="datetime-local" name="unpublish_at" class="bx-input" value="<?= e(!empty($editFolder['unpublish_at']) ? date('Y-m-d\TH:i', strtotime((string) $editFolder['unpublish_at'])) : '') ?>">
                    </div>
                </div>

                <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:600;">
                    <input type="checkbox" name="is_active" value="1" <?= !$editFolder || (int) $editFolder['is_active'] ? 'checked' : '' ?>>
                    <?= $lang === 'bn' ? 'পাবলিক সাইটে দৃশ্যমান রাখুন' : 'Active & visible on public site' ?>
                </label>

                <div class="bx-sticky-form-actions">
                    <button type="submit" class="bx-btn bx-btn-primary">
                        <?= $editFolder ? ($lang === 'bn' ? 'ফোল্ডার আপডেট করুন' : 'Update Folder') : ($lang === 'bn' ? 'ফোল্ডার সংরক্ষণ করুন' : 'Create Folder') ?>
                    </button>
                    <button type="button" class="bx-btn bx-btn-ghost" data-close-sheet><?= $lang === 'bn' ? 'বাতিল' : 'Close' ?></button>
                </div>
            </form>
        </section>
    </div>

<!-- Move To... Modal -->
<div class="bx-modal-backdrop" id="bx-folder-move-modal" role="dialog" aria-modal="true">
    <div class="bx-modal" style="max-width:440px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <h3 style="margin:0;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'ফোল্ডার স্থানান্তর করুন' : 'Move Folder' ?></h3>
            <button type="button" class="bx-btn bx-btn-ghost" data-modal-close aria-label="Close" style="min-width:44px;min-height:44px;">✕</button>
        </div>
        <p style="color:var(--text-muted);margin:0 0 12px;">
            <?= $lang === 'bn' ? 'নির্বাচিত ফোল্ডার:' : 'Moving:' ?> <strong id="bx-move-folder-name"></strong>
        </p>
        <form method="post" action="<?= e(url('/admin/folders')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="move_folder">
            <input type="hidden" name="id" id="bx-move-folder-id" value="">
            <div class="bx-field">
                <label><?= $lang === 'bn' ? 'নতুন প্যারেন্ট নির্বাচন করুন' : 'Select New Parent' ?></label>
                <select name="parent_id" id="bx-move-parent-select" class="bx-input">
                    <option value=""><?= $lang === 'bn' ? 'মূল স্তর (Root Level)' : 'Root (Top Level)' ?></option>
                    <?php foreach ($allFolderOptions as $opt): ?>
                        <option value="<?= $opt['id'] ?>"><?= e($opt['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="bx-btn bx-btn-ghost" data-modal-close><?= $lang === 'bn' ? 'বাতিল' : 'Cancel' ?></button>
                <button type="submit" class="bx-btn bx-btn-primary"><?= $lang === 'bn' ? 'সরান' : 'Move Folder' ?></button>
            </div>
        </form>
    </div>
</div>
