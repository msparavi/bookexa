<?php
declare(strict_types=1);

$lang = current_lang();
$editId = max(0, (int) ($_GET['edit'] ?? 0));
$editCat = $editId > 0 ? db_one('SELECT * FROM shop_categories WHERE id = ? LIMIT 1', [$editId]) : null;
$categories = shop_categories_flat_tree(false);

// Count products per category
$prodCountsRaw = db_all('SELECT category_id, COUNT(*) AS cnt FROM products WHERE category_id IS NOT NULL GROUP BY category_id');
$prodCounts = [];
foreach ($prodCountsRaw as $r) {
    $prodCounts[(int) $r['category_id']] = (int) $r['cnt'];
}
?>

<div class="bx-grid-2" style="align-items:start;">
    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:14px;">
            <h2 style="margin:0;font-size:1.12rem;font-family:var(--font-serif);">
                <?= $editCat
                    ? ($lang === 'bn' ? 'ক্যাটাগরি সম্পাদনা' : 'Edit Category')
                    : ($lang === 'bn' ? 'নতুন শপ ক্যাটাগরি' : 'New Product Category') ?>
            </h2>
            <?php if ($editCat): ?>
                <a href="<?= e(url('/admin/shop-categories')) ?>" class="bx-btn bx-btn-ghost bx-btn-sm"><?= $lang === 'bn' ? '+ নতুন' : '+ New' ?></a>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= e(url('/admin/shop-categories')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_shop_category">
            <input type="hidden" name="id" value="<?= (int) ($editCat['id'] ?? 0) ?>">

            <div class="bx-field">
                <label><?= $lang === 'bn' ? 'প্যারেন্ট ক্যাটাগরি (ঐচ্ছিক)' : 'Parent Category (Optional)' ?></label>
                <select name="parent_id" class="bx-input">
                    <option value="0"><?= $lang === 'bn' ? '— রুট ক্যাটাগরি —' : '— Top Level Category —' ?></option>
                    <?php foreach ($categories as $c): ?>
                        <?php if ($editCat && (int) $c['id'] === (int) $editCat['id']) continue; ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($editCat['parent_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e(str_repeat('— ', (int) $c['_depth']) . ($lang === 'bn' ? $c['name_bn'] : $c['name_en'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="bx-grid-2" style="gap:10px;">
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'নাম (বাংলা) *' : 'Name (Bangla) *' ?></label>
                    <input type="text" name="name_bn" class="bx-input" required value="<?= e((string) ($editCat['name_bn'] ?? '')) ?>">
                </div>
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'নাম (English) *' : 'Name (English) *' ?></label>
                    <input type="text" name="name_en" class="bx-input" required value="<?= e((string) ($editCat['name_en'] ?? '')) ?>">
                </div>
            </div>

            <div class="bx-grid-2" style="gap:10px;">
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'স্লাগ (Slug)' : 'URL Slug' ?></label>
                    <input type="text" name="slug" class="bx-input" placeholder="auto-from-english-name" value="<?= e((string) ($editCat['slug'] ?? '')) ?>">
                </div>
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'সর্ট অর্ডার' : 'Sort Order' ?></label>
                    <input type="number" name="sort_order" class="bx-input" value="<?= (int) ($editCat['sort_order'] ?? 0) ?>">
                </div>
            </div>

            <div class="bx-field">
                <label><?= $lang === 'bn' ? 'থাম্বনেইল ছবি' : 'Thumbnail Image (JPG/PNG/WebP)' ?></label>
                <input type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp" class="bx-input" data-preview-target="shop-cat-thumb-preview">
                <?php if (!empty($editCat['thumbnail'])): ?>
                    <img id="shop-cat-thumb-preview" src="<?= e(asset((string) $editCat['thumbnail'])) ?>" alt="" class="bx-live-preview" style="max-height:72px;margin-top:8px;">
                <?php endif; ?>
            </div>

            <div class="bx-grid-2" style="gap:10px;">
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'বিবরণ (বাংলা)' : 'Description (Bangla)' ?></label>
                    <textarea name="description_bn" rows="2" class="bx-input"><?= e((string) ($editCat['description_bn'] ?? '')) ?></textarea>
                </div>
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'বিবরণ (English)' : 'Description (English)' ?></label>
                    <textarea name="description_en" rows="2" class="bx-input"><?= e((string) ($editCat['description_en'] ?? '')) ?></textarea>
                </div>
            </div>

            <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:600;margin-bottom:10px;">
                <input type="checkbox" name="is_active" value="1" <?= (!isset($editCat['is_active']) || (int) $editCat['is_active'] === 1) ? 'checked' : '' ?>>
                <?= $lang === 'bn' ? 'সক্রিয় রাখুন' : 'Active in Shop' ?>
            </label>

            <button type="submit" class="bx-btn bx-btn-primary" style="width:100%;">
                <?= $editCat ? ($lang === 'bn' ? 'আপডেট সংরক্ষণ করুন' : 'Update Category') : ($lang === 'bn' ? 'ক্যাটাগরি তৈরি করুন' : 'Create Category') ?>
            </button>
        </form>
    </section>

    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:12px;">
            <h2 style="margin:0;font-size:1.12rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'সকল ক্যাটাগরি' : 'Category Tree' ?> (<?= count($categories) ?>)</h2>
            <a href="<?= e(url('/shop')) ?>" target="_blank" rel="noopener" class="bx-btn bx-btn-ghost bx-btn-sm"><?= $lang === 'bn' ? 'শপ দেখুন →' : 'View Shop →' ?></a>
        </div>

        <div class="bx-table-wrap">
            <table class="bx-table bx-mobile-cards">
                <thead>
                    <tr>
                        <th><?= $lang === 'bn' ? 'ক্যাটাগরি' : 'Category' ?></th>
                        <th><?= $lang === 'bn' ? 'পণ্য' : 'Products' ?></th>
                        <th><?= $lang === 'bn' ? 'অবস্থা' : 'Status' ?></th>
                        <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Actions' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$categories): ?>
                        <tr class="bx-empty-row"><td colspan="4" style="padding:20px;color:var(--text-muted);"><?= $lang === 'bn' ? 'কোনো ক্যাটাগরি নেই।' : 'No shop categories created yet.' ?></td></tr>
                    <?php else: foreach ($categories as $c): $cid = (int) $c['id']; ?>
                        <tr>
                            <td class="bx-td-primary">
                                <div style="display:flex;align-items:center;gap:10px;padding-left:<?= min(24, (int) $c['_depth'] * 12) ?>px;">
                                    <?php if (!empty($c['thumbnail'])): ?>
                                        <img src="<?= e(asset((string) $c['thumbnail'])) ?>" alt="" width="36" height="36" style="width:36px;height:36px;object-fit:cover;border-radius:8px;flex-shrink:0;">
                                    <?php endif; ?>
                                    <div style="min-width:0;">
                                        <strong style="display:block;"><?= e($lang === 'bn' ? $c['name_bn'] : $c['name_en']) ?></strong>
                                        <small style="color:var(--text-muted);display:block;overflow:hidden;text-overflow:ellipsis;">
                                            <?= e($lang === 'bn' ? $c['name_en'] : $c['name_bn']) ?> • /<?= e($c['slug']) ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="<?= e($lang === 'bn' ? 'পণ্য' : 'Products') ?>">
                                <span class="bx-badge"><?= $prodCounts[$cid] ?? 0 ?></span>
                            </td>
                            <td data-label="<?= e($lang === 'bn' ? 'অবস্থা' : 'Status') ?>">
                                <span class="bx-status-pill <?= (int) $c['is_active'] === 1 ? 'st-approved' : 'st-inactive' ?>">
                                    <?= (int) $c['is_active'] === 1 ? ($lang === 'bn' ? 'সক্রিয়' : 'Active') : ($lang === 'bn' ? 'নিষ্ক্রিয়' : 'Hidden') ?>
                                </span>
                            </td>
                            <td class="bx-td-actions">
                                <a href="<?= e(url('/admin/shop-categories?edit=' . $cid)) ?>" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'এডিট' : 'Edit' ?></a>
                                <form method="post" action="<?= e(url('/admin/shop-categories')) ?>" data-confirm-msg="<?= e($lang === 'bn' ? 'এই ক্যাটাগরি মুছে ফেলবেন?' : 'Delete this category? Subcategories/products will be detached.') ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_shop_category">
                                    <input type="hidden" name="id" value="<?= $cid ?>">
                                    <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm" style="color:var(--rose-text);"><?= $lang === 'bn' ? 'মুছুন' : 'Delete' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
