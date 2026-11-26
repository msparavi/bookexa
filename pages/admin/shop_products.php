<?php
declare(strict_types=1);

$lang = current_lang();
$editId = max(0, (int) ($_GET['edit'] ?? 0));
$histId = max(0, (int) ($_GET['stock_history'] ?? 0));
$editProd = $editId > 0 ? db_one('SELECT * FROM products WHERE id = ? LIMIT 1', [$editId]) : null;
$editImages = $editProd ? shop_product_images((int) $editProd['id']) : [];

$categories = shop_categories_flat_tree(false);
$lowStockList = shop_low_stock_products(25);

$fStatus = (string) ($_GET['status'] ?? 'all');
$fCat    = max(0, (int) ($_GET['category_id'] ?? 0));
$fQ      = trim((string) ($_GET['q'] ?? ''));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$where = [];
$bind  = [];
if ($fStatus === 'active') {
    $where[] = 'p.is_active = 1';
} elseif ($fStatus === 'inactive') {
    $where[] = 'p.is_active = 0';
} elseif ($fStatus === 'low_stock') {
    $where[] = 'p.stock > 0 AND p.stock <= p.low_stock_threshold';
} elseif ($fStatus === 'out_of_stock') {
    $where[] = 'p.stock <= 0';
} elseif ($fStatus === 'featured') {
    $where[] = 'p.is_featured = 1';
}
if ($fCat > 0) {
    $where[] = 'p.category_id = ?';
    $bind[] = $fCat;
}
if ($fQ !== '') {
    $like = '%' . escape_like($fQ) . '%';
    $where[] = "(p.title_bn LIKE ? ESCAPE '\\\\' OR p.title_en LIKE ? ESCAPE '\\\\' OR COALESCE(p.author_en,'') LIKE ? ESCAPE '\\\\' OR COALESCE(p.sku,'') LIKE ? ESCAPE '\\\\' OR COALESCE(p.isbn,'') LIKE ? ESCAPE '\\\\')";
    array_push($bind, $like, $like, $like, $like, $like);
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$totalRows = (int) (db_one("SELECT COUNT(*) AS c FROM products p {$whereSql}", $bind)['c'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$products = db_all(
    "SELECT p.*, sc.name_bn AS cat_bn, sc.name_en AS cat_en
     FROM products p
     LEFT JOIN shop_categories sc ON sc.id = p.category_id
     {$whereSql}
     ORDER BY p.id DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $bind
);

$historyTargetId = $histId > 0 ? $histId : ($editProd ? (int) $editProd['id'] : 0);
$stockLogs = $historyTargetId > 0
    ? db_all(
        'SELECT l.*, a.name AS admin_name
         FROM product_stock_logs l
         LEFT JOIN admins a ON a.id = l.admin_id
         WHERE l.product_id = ?
         ORDER BY l.id DESC LIMIT 40',
        [$historyTargetId]
    )
    : [];
$historyProd = $historyTargetId > 0 ? db_one('SELECT id, title_bn, title_en, stock FROM products WHERE id = ?', [$historyTargetId]) : null;

$csvPreview = $_SESSION['shop_csv_preview'] ?? null;
$curUrl = $_SERVER['REQUEST_URI'] ?? '/admin/shop-products';
$buildProdUrl = static function (array $ov = []) use ($fStatus, $fCat, $fQ, $page): string {
    $q = array_filter([
        'status'      => $ov['status'] ?? $fStatus,
        'category_id' => $ov['category_id'] ?? ($fCat > 0 ? (string) $fCat : ''),
        'q'           => $ov['q'] ?? $fQ,
        'page'        => $ov['page'] ?? ($page > 1 ? (string) $page : ''),
    ], static fn($v): bool => $v !== '' && $v !== 'all');
    return url('/admin/shop-products' . ($q ? ('?' . http_build_query($q)) : ''));
};
?>

<?php if ($lowStockList): ?>
    <div class="bx-alert bx-alert-warning" style="margin-bottom:16px;padding:12px 16px;border-radius:12px;background:var(--surface);border:1px solid var(--border);border-left:4px solid var(--gold);display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div>
            <strong><?= $lang === 'bn' ? 'কম স্টক সতর্কতা!' : 'Low Stock Alert!' ?></strong>
            <?= count($lowStockList) ?> <?= $lang === 'bn' ? 'টি বইয়ের স্টক থ্রেশহোল্ডের নিচে বা শেষ।' : 'product(s) are low or out of stock.' ?>
        </div>
        <a href="<?= e($buildProdUrl(['status' => 'low_stock', 'page' => '1'])) ?>" class="bx-btn bx-btn-outline bx-btn-sm">
            <?= $lang === 'bn' ? 'কম স্টক দেখুন' : 'View Low Stock' ?>
        </a>
    </div>
<?php endif; ?>

<!-- Toolbar + CSV Export/Import -->
<section class="bx-card" style="margin-bottom:18px;padding:14px 18px;">
    <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach (['all' => ($lang === 'bn' ? 'সবগুলো' : 'All'), 'active' => ($lang === 'bn' ? 'সক্রিয়' : 'Active'), 'low_stock' => ($lang === 'bn' ? 'কম স্টক' : 'Low Stock'), 'out_of_stock' => ($lang === 'bn' ? 'স্টক শেষ' : 'Out of Stock'), 'featured' => ($lang === 'bn' ? 'ফিচার্ড' : 'Featured')] as $stKey => $stLbl): ?>
                <a href="<?= e($buildProdUrl(['status' => $stKey, 'page' => '1'])) ?>" class="bx-btn bx-btn-sm <?= $fStatus === $stKey ? 'bx-btn-primary' : 'bx-btn-outline' ?>"><?= e($stLbl) ?></a>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?= e(url('/admin/shop-products?sample=1')) ?>" class="bx-btn bx-btn-ghost bx-btn-sm">↓ <?= $lang === 'bn' ? 'নমুনা CSV' : 'Sample CSV' ?></a>
            <a href="<?= e(url('/admin/shop-products?export=csv')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">↓ <?= $lang === 'bn' ? 'CSV এক্সপোর্ট' : 'Export CSV' ?></a>
        </div>
    </div>

    <form method="get" action="<?= e(url('/admin/shop-products')) ?>" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,180px),1fr));gap:10px;align-items:end;">
        <input type="hidden" name="status" value="<?= e($fStatus) ?>">
        <div>
            <label style="display:block;font-size:0.78rem;font-weight:600;margin-bottom:3px;"><?= $lang === 'bn' ? 'খুঁজুন (নাম, SKU, ISBN)' : 'Search (Title, SKU, ISBN)' ?></label>
            <input type="search" name="q" class="bx-input" value="<?= e($fQ) ?>" placeholder="<?= e($lang === 'bn' ? 'টাইটেল, লেখক বা SKU...' : 'Title, author or SKU...') ?>">
        </div>
        <div>
            <label style="display:block;font-size:0.78rem;font-weight:600;margin-bottom:3px;"><?= $lang === 'bn' ? 'ক্যাটাগরি' : 'Category' ?></label>
            <select name="category_id" class="bx-input">
                <option value="0"><?= $lang === 'bn' ? 'সব ক্যাটাগরি' : 'All Categories' ?></option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $fCat === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= e(str_repeat('— ', (int) $c['_depth']) . ($lang === 'bn' ? $c['name_bn'] : $c['name_en'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:8px;">
            <button type="submit" class="bx-btn bx-btn-primary" style="flex:1;"><?= $lang === 'bn' ? 'ফিল্টার' : 'Filter' ?></button>
            <?php if ($fQ !== '' || $fCat > 0 || $fStatus !== 'all'): ?>
                <a href="<?= e(url('/admin/shop-products')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'রিসেট' : 'Reset' ?></a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Collapsible Bulk CSV Import -->
    <details <?= $csvPreview ? 'open' : '' ?> style="margin-top:12px;padding-top:12px;border-top:1px dashed var(--border);">
        <summary style="cursor:pointer;font-weight:700;font-size:0.9rem;color:var(--primary);">
            <?= $lang === 'bn' ? 'CSV দিয়ে বাল্ক পণ্য ইমপোর্ট করুন' : 'Bulk CSV Import Products' ?>
        </summary>
        <form method="post" action="<?= e(url('/admin/shop-products')) ?>" enctype="multipart/form-data" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:10px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="preview_shop_csv">
            <input type="file" name="csv_file" accept=".csv,text/csv" class="bx-input" required style="flex:1 1 240px;">
            <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'প্রিভিউ ও যাচাই করুন' : 'Preview CSV' ?></button>
        </form>
        <?php if (is_array($csvPreview) && !empty($csvPreview['rows'])): ?>
            <div style="margin-top:12px;padding:12px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                    <strong>Preview: <?= (int) $csvPreview['summary']['valid'] ?> valid / <?= (int) $csvPreview['summary']['errors'] ?> error(s)</strong>
                    <form method="post" action="<?= e(url('/admin/shop-products')) ?>" style="margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="import_shop_csv">
                        <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm" <?= (int) $csvPreview['summary']['valid'] === 0 ? 'disabled' : '' ?>>
                            <?= $lang === 'bn' ? 'সঠিক সারিগুলো ইমপোর্ট করুন' : 'Import Valid Products' ?>
                        </button>
                    </form>
                </div>
                <div class="bx-table-wrap" style="max-height:220px;overflow:auto;">
                    <table class="bx-table">
                        <thead><tr><th>Line</th><th>Title</th><th>SKU</th><th>Price</th><th>Stock</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($csvPreview['rows'] as $cr): ?>
                                <tr>
                                    <td><?= (int) $cr['line'] ?></td>
                                    <td><?= e((string) $cr['title_en']) ?></td>
                                    <td><?= e((string) ($cr['sku'] ?? '—')) ?></td>
                                    <td>৳<?= number_format((float) $cr['price'], 0) ?></td>
                                    <td><?= (int) $cr['stock'] ?></td>
                                    <td>
                                        <?php if (empty($cr['errors'])): ?>
                                            <span class="bx-status-pill st-approved">Ready</span>
                                        <?php else: ?>
                                            <span class="bx-status-pill st-rejected"><?= e(implode('; ', $cr['errors'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </details>
</section>

<!-- Add / Edit Product + Multi-Image Gallery + Stock Adjustment -->
<div class="bx-grid-2" style="align-items:start;margin-bottom:20px;">
    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:14px;">
            <h2 style="margin:0;font-size:1.12rem;font-family:var(--font-serif);">
                <?= $editProd ? ($lang === 'bn' ? 'পণ্য সম্পাদনা' : 'Edit Product') : ($lang === 'bn' ? 'নতুন প্রিন্টেড বই যোগ করুন' : 'Add Physical Book / Product') ?>
            </h2>
            <?php if ($editProd): ?>
                <a href="<?= e(url('/admin/shop-products')) ?>" class="bx-btn bx-btn-ghost bx-btn-sm">+ <?= $lang === 'bn' ? 'নতুন' : 'New' ?></a>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= e(url('/admin/shop-products')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_shop_product">
            <input type="hidden" name="id" value="<?= (int) ($editProd['id'] ?? 0) ?>">
            <input type="hidden" name="_return" value="<?= e($curUrl) ?>">

            <div class="bx-grid-2" style="gap:10px;">
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'বইয়ের নাম (বাংলা) *' : 'Title (Bangla) *' ?></label>
                    <input type="text" name="title_bn" class="bx-input" required value="<?= e((string) ($editProd['title_bn'] ?? '')) ?>">
                </div>
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'বইয়ের নাম (English) *' : 'Title (English) *' ?></label>
                    <input type="text" name="title_en" class="bx-input" required value="<?= e((string) ($editProd['title_en'] ?? '')) ?>">
                </div>
            </div>

            <div class="bx-grid-2" style="gap:10px;">
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'ক্যাটাগরি' : 'Category' ?></label>
                    <select name="category_id" class="bx-input">
                        <option value="0"><?= $lang === 'bn' ? '— অনির্ধারিত —' : '— Uncategorized —' ?></option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) ($editProd['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= e(str_repeat('— ', (int) $c['_depth']) . ($lang === 'bn' ? $c['name_bn'] : $c['name_en'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'স্লাগ (Slug)' : 'Slug' ?></label>
                    <input type="text" name="slug" class="bx-input" placeholder="auto-from-english-title" value="<?= e((string) ($editProd['slug'] ?? '')) ?>">
                </div>
            </div>

            <div class="bx-grid-2" style="gap:10px;">
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'লেখক (বাংলা)' : 'Author (Bangla)' ?></label>
                    <input type="text" name="author_bn" class="bx-input" value="<?= e((string) ($editProd['author_bn'] ?? '')) ?>">
                </div>
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'লেখক (English)' : 'Author (English)' ?></label>
                    <input type="text" name="author_en" class="bx-input" value="<?= e((string) ($editProd['author_en'] ?? '')) ?>">
                </div>
            </div>

            <div class="bx-grid-3" style="gap:10px;">
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'প্রকাশক' : 'Publisher' ?></label>
                    <input type="text" name="publisher" class="bx-input" value="<?= e((string) ($editProd['publisher'] ?? '')) ?>">
                </div>
                <div class="bx-field">
                    <label>ISBN</label>
                    <input type="text" name="isbn" class="bx-input" value="<?= e((string) ($editProd['isbn'] ?? '')) ?>">
                </div>
                <div class="bx-field">
                    <label>SKU</label>
                    <input type="text" name="sku" class="bx-input" value="<?= e((string) ($editProd['sku'] ?? '')) ?>">
                </div>
            </div>

            <div class="bx-grid-3" style="gap:10px;">
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'মূল্য (৳) *' : 'Regular Price (৳) *' ?></label>
                    <input type="number" step="1" min="1" name="price" class="bx-input" required value="<?= e((string) ($editProd['price'] ?? '')) ?>">
                </div>
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'ডিসকাউন্ট মূল্য (৳)' : 'Discount Price (৳)' ?></label>
                    <input type="number" step="1" min="0" name="discount_price" class="bx-input" value="<?= e((string) ($editProd['discount_price'] ?? '')) ?>">
                </div>
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'ওজন (গ্রাম)' : 'Weight (grams)' ?></label>
                    <input type="number" min="10" name="weight_grams" class="bx-input" value="<?= (int) ($editProd['weight_grams'] ?? 350) ?>">
                </div>
            </div>

            <div class="bx-grid-2" style="gap:10px;">
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'স্টক পরিমাণ' : 'Stock Qty' ?></label>
                    <input type="number" min="0" name="stock" class="bx-input" value="<?= (int) ($editProd['stock'] ?? 10) ?>">
                </div>
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'কম স্টক সতর্কতা সীমা' : 'Low Stock Alert At' ?></label>
                    <input type="number" min="0" name="low_stock_threshold" class="bx-input" value="<?= (int) ($editProd['low_stock_threshold'] ?? 5) ?>">
                </div>
            </div>

            <div class="bx-field">
                <label><?= $lang === 'bn' ? 'বইয়ের ছবি (একাধিক ছবি দিতে পারবেন; প্রথমটি কভার)' : 'Product Images (Multiple allowed; first becomes cover)' ?></label>
                <input type="file" name="gallery_images[]" accept="image/jpeg,image/png,image/webp" multiple class="bx-input">
            </div>

            <div class="bx-grid-2" style="gap:10px;">
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'বিবরণ (বাংলা)' : 'Description (Bangla)' ?></label>
                    <textarea name="description_bn" rows="3" class="bx-input"><?= e((string) ($editProd['description_bn'] ?? '')) ?></textarea>
                </div>
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'বিবরণ (English)' : 'Description (English)' ?></label>
                    <textarea name="description_en" rows="3" class="bx-input"><?= e((string) ($editProd['description_en'] ?? '')) ?></textarea>
                </div>
            </div>

            <details style="margin-bottom:12px;padding:10px 12px;border:1px solid var(--border);border-radius:10px;background:var(--bg-alt);">
                <summary style="cursor:pointer;font-weight:700;font-size:0.88rem;"><?= $lang === 'bn' ? 'SEO মেটাডাটা (ঐচ্ছিক)' : 'SEO Metadata (Optional — overrides template)' ?></summary>
                <div class="bx-grid-2" style="gap:10px;margin-top:8px;">
                    <div>
                        <label style="font-size:0.82rem;font-weight:600;">SEO Title (BN)</label>
                        <input type="text" name="seo_title_bn" class="bx-input" maxlength="220" value="<?= e((string) ($editProd['seo_title_bn'] ?? '')) ?>">
                    </div>
                    <div>
                        <label style="font-size:0.82rem;font-weight:600;">SEO Title (EN)</label>
                        <input type="text" name="seo_title_en" class="bx-input" maxlength="220" value="<?= e((string) ($editProd['seo_title_en'] ?? '')) ?>">
                    </div>
                    <div>
                        <label style="font-size:0.82rem;font-weight:600;">SEO Description (BN)</label>
                        <textarea name="seo_description_bn" rows="2" class="bx-input" maxlength="320"><?= e((string) ($editProd['seo_description_bn'] ?? '')) ?></textarea>
                    </div>
                    <div>
                        <label style="font-size:0.82rem;font-weight:600;">SEO Description (EN)</label>
                        <textarea name="seo_description_en" rows="2" class="bx-input" maxlength="320"><?= e((string) ($editProd['seo_description_en'] ?? '')) ?></textarea>
                    </div>
                </div>
            </details>

            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px;">
                <label style="display:flex;align-items:center;gap:8px;min-height:40px;font-weight:600;">
                    <input type="checkbox" name="is_active" value="1" <?= (!isset($editProd['is_active']) || (int) $editProd['is_active'] === 1) ? 'checked' : '' ?>>
                    <?= $lang === 'bn' ? 'শপে সক্রিয়' : 'Active' ?>
                </label>
                <label style="display:flex;align-items:center;gap:8px;min-height:40px;font-weight:600;">
                    <input type="checkbox" name="is_featured" value="1" <?= !empty($editProd['is_featured']) ? 'checked' : '' ?>>
                    <?= $lang === 'bn' ? 'ফিচার্ড বই' : 'Featured' ?>
                </label>
            </div>

            <button type="submit" class="bx-btn bx-btn-primary" style="width:100%;">
                <?= $editProd ? ($lang === 'bn' ? 'পণ্য আপডেট করুন' : 'Update Product') : ($lang === 'bn' ? 'পণ্য যুক্ত করুন' : 'Save New Product') ?>
            </button>
        </form>
    </section>

    <div style="display:flex;flex-direction:column;gap:18px;">
        <?php if ($editProd && $editImages): ?>
            <section class="bx-card">
                <h2 style="margin:0 0 12px;font-size:1.08rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'গ্যালারি ও কভার ইমেজ' : 'Gallery & Cover Management' ?></h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;">
                    <?php foreach ($editImages as $img): ?>
                        <div style="border:1px solid var(--border);border-radius:10px;padding:8px;background:var(--bg-alt);text-align:center;">
                            <img src="<?= e(asset((string) $img['image_path'])) ?>" alt="" style="width:100%;aspect-ratio:3/4;object-fit:cover;border-radius:8px;">
                            <div style="display:flex;gap:4px;justify-content:center;margin-top:6px;flex-wrap:wrap;">
                                <?php if ((int) $img['is_cover'] === 1): ?>
                                    <span class="bx-badge bx-badge-teal">Cover</span>
                                <?php elseif ((int) $img['id'] > 0): ?>
                                    <form method="post" action="<?= e(url('/admin/shop-products')) ?>" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="set_shop_product_cover">
                                        <input type="hidden" name="product_id" value="<?= (int) $editProd['id'] ?>">
                                        <input type="hidden" name="image_id" value="<?= (int) $img['id'] ?>">
                                        <input type="hidden" name="_return" value="<?= e($curUrl) ?>">
                                        <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm">Cover</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ((int) $img['id'] > 0): ?>
                                    <form method="post" action="<?= e(url('/admin/shop-products')) ?>" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_shop_product_image">
                                        <input type="hidden" name="product_id" value="<?= (int) $editProd['id'] ?>">
                                        <input type="hidden" name="image_id" value="<?= (int) $img['id'] ?>">
                                        <input type="hidden" name="_return" value="<?= e($curUrl) ?>">
                                        <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm" style="color:var(--rose-text);">✕</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($historyProd): ?>
            <section class="bx-card" id="stock-history-box">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px;">
                    <h2 style="margin:0;font-size:1.08rem;font-family:var(--font-serif);">
                        <?= $lang === 'bn' ? 'স্টক পরিবর্তন ও হিস্ট্রি' : 'Stock Adjustment & History' ?>
                    </h2>
                    <span class="bx-badge"><?= e($lang === 'bn' ? 'বর্তমান স্টক: ' : 'Stock: ') ?><?= (int) $historyProd['stock'] ?></span>
                </div>
                <p style="margin:0 0 10px;font-weight:700;color:var(--primary);"><?= e($lang === 'bn' ? $historyProd['title_bn'] : $historyProd['title_en']) ?></p>
                <form method="post" action="<?= e(url('/admin/shop-products')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="adjust_shop_stock">
                    <input type="hidden" name="product_id" value="<?= (int) $historyProd['id'] ?>">
                    <input type="hidden" name="_return" value="<?= e($curUrl) ?>">
                    <input type="number" name="delta" class="bx-input" required placeholder="+10 or -2" style="width:110px;flex:0 1 110px;">
                    <input type="text" name="reason" class="bx-input" required placeholder="<?= e($lang === 'bn' ? 'কারণ (যেমন: নতুন স্টক)' : 'Reason (e.g. New print batch)') ?>" style="flex:1 1 160px;min-width:0;">
                    <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= $lang === 'bn' ? 'আপডেট' : 'Apply' ?></button>
                </form>

                <div class="bx-table-wrap" style="max-height:280px;overflow:auto;">
                    <table class="bx-table">
                        <thead><tr><th>Δ Qty</th><th>Before → After</th><th>Reason</th><th>By / Date</th></tr></thead>
                        <tbody>
                            <?php if (!$stockLogs): ?>
                                <tr><td colspan="4" style="color:var(--text-muted);padding:12px;"><?= $lang === 'bn' ? 'কোনো স্টক লগ নেই।' : 'No stock logs yet.' ?></td></tr>
                            <?php else: foreach ($stockLogs as $sl): ?>
                                <tr>
                                    <td><strong style="color:<?= (int) $sl['change_qty'] >= 0 ? 'var(--teal)' : 'var(--rose-text)' ?>;"><?= (int) $sl['change_qty'] > 0 ? '+' . (int) $sl['change_qty'] : (int) $sl['change_qty'] ?></strong></td>
                                    <td><?= (int) $sl['stock_before'] ?> → <?= (int) $sl['stock_after'] ?></td>
                                    <td><?= e((string) $sl['reason']) ?></td>
                                    <td><small><?= e((string) ($sl['admin_name'] ?? 'System')) ?><br><?= e(date('d M, H:i', strtotime((string) $sl['created_at']))) ?></small></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>

<!-- Products Table -->
<section class="bx-card">
    <h2 style="margin:0 0 12px;font-size:1.12rem;font-family:var(--font-serif);">
        <?= $lang === 'bn' ? 'সকল শপ পণ্য' : 'Shop Inventory' ?> (<?= $totalRows ?>)
    </h2>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'বই ও বিবরণ' : 'Product' ?></th>
                    <th><?= $lang === 'bn' ? 'মূল্য' : 'Price' ?></th>
                    <th><?= $lang === 'bn' ? 'স্টক ও বিক্রি' : 'Stock & Sales' ?></th>
                    <th><?= $lang === 'bn' ? 'অবস্থা' : 'Status' ?></th>
                    <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Actions' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$products): ?>
                    <tr class="bx-empty-row"><td colspan="5" style="padding:24px;color:var(--text-muted);"><?= $lang === 'bn' ? 'কোনো পণ্য পাওয়া যায়নি।' : 'No products found.' ?></td></tr>
                <?php else: foreach ($products as $p): $pid = (int) $p['id']; $eff = shop_product_effective_price($p); ?>
                    <tr>
                        <td class="bx-td-primary">
                            <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                                <img src="<?= e(!empty($p['cover_image']) ? asset((string) $p['cover_image']) : asset('uploads/covers/cover-bangla.svg')) ?>" alt="" width="40" height="54" style="width:40px;height:54px;object-fit:cover;border-radius:6px;flex-shrink:0;">
                                <div style="min-width:0;">
                                    <a href="<?= e(url('/shop/' . rawurlencode($p['slug']))) ?>" target="_blank" rel="noopener" style="font-weight:700;color:var(--primary);display:block;overflow-wrap:anywhere;">
                                        <?= e($lang === 'bn' ? $p['title_bn'] : $p['title_en']) ?>
                                    </a>
                                    <small style="color:var(--text-muted);display:block;">
                                        <?= e((string) ($lang === 'bn' ? ($p['cat_bn'] ?? '') : ($p['cat_en'] ?? ''))) ?>
                                        <?= !empty($p['sku']) ? (' • SKU: ' . e((string) $p['sku'])) : '' ?>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'মূল্য' : 'Price') ?>">
                            <strong>৳<?= number_format($eff, 0) ?></strong>
                            <?php if ($eff < (float) $p['price']): ?>
                                <small style="text-decoration:line-through;color:var(--text-muted);">৳<?= number_format((float) $p['price'], 0) ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'স্টক' : 'Stock') ?>">
                            <?php
                            $stk = (int) $p['stock'];
                            $lowT = (int) $p['low_stock_threshold'];
                            $stkPill = $stk <= 0 ? 'st-rejected' : ($stk <= $lowT ? 'st-pending' : 'st-approved');
                            ?>
                            <span class="bx-status-pill <?= $stkPill ?>"><?= $stk ?> in stock</span>
                            <div style="font-size:0.78rem;color:var(--text-muted);margin-top:3px;"><?= (int) $p['sales_count'] ?> sold</div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'অবস্থা' : 'Status') ?>">
                            <span class="bx-status-pill <?= (int) $p['is_active'] === 1 ? 'st-approved' : 'st-inactive' ?>">
                                <?= (int) $p['is_active'] === 1 ? ($lang === 'bn' ? 'সক্রিয়' : 'Active') : ($lang === 'bn' ? 'নিষ্ক্রিয়' : 'Hidden') ?>
                            </span>
                            <?php if ((int) $p['is_featured'] === 1): ?>
                                <span class="bx-badge bx-badge-gold" style="margin-top:4px;display:inline-block;">★ Featured</span>
                            <?php endif; ?>
                        </td>
                        <td class="bx-td-actions">
                            <a href="<?= e(url('/admin/shop-products?edit=' . $pid)) ?>" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'এডিট' : 'Edit' ?></a>
                            <a href="<?= e(url('/admin/shop-products?stock_history=' . $pid)) ?>#stock-history-box" class="bx-btn bx-btn-ghost bx-btn-sm"><?= $lang === 'bn' ? 'স্টক' : 'Stock' ?></a>
                            <form method="post" action="<?= e(url('/admin/shop-products')) ?>" data-confirm-msg="<?= e($lang === 'bn' ? 'এই পণ্যটি মুছে ফেলবেন?' : 'Delete this product permanently?') ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_shop_product">
                                <input type="hidden" name="id" value="<?= $pid ?>">
                                <input type="hidden" name="_return" value="<?= e($curUrl) ?>">
                                <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm" style="color:var(--rose-text);"><?= $lang === 'bn' ? 'মুছুন' : 'Delete' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="bx-pagination" aria-label="Products Pagination">
            <span style="font-size:0.85rem;color:var(--text-muted);">Page <?= $page ?> of <?= $totalPages ?></span>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php if ($page > 1): ?>
                    <a href="<?= e($buildProdUrl(['page' => (string) ($page - 1)])) ?>">« Prev</a>
                <?php endif; ?>
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <a href="<?= e($buildProdUrl(['page' => (string) $p])) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= e($buildProdUrl(['page' => (string) ($page + 1)])) ?>">Next »</a>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</section>
