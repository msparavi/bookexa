<?php
declare(strict_types=1);

$lang = current_lang();
$previewMeta = $_SESSION['csv_import_preview'] ?? null;
$previewRows = [];
$previewSummary = ['total' => 0, 'valid' => 0, 'errors' => 0, 'new_folders' => 0];
$previewValid = false;
if (is_array($previewMeta) && !empty($previewMeta['file']) && is_file((string) $previewMeta['file'])) {
    $rawJson = @file_get_contents((string) $previewMeta['file']);
    $decoded = $rawJson !== false ? json_decode($rawJson, true) : null;
    if (is_array($decoded)) {
        $previewRows = $decoded['rows'] ?? [];
        $previewSummary = $decoded['summary'] ?? $previewSummary;
        $previewValid = true;
    }
}
?>
<section class="bx-card" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <div>
            <h2 style="margin:0;font-size:1.15rem;font-family:var(--font-serif);">
                <?= $lang === 'bn' ? 'CSV বাল্ক বই ইমপোর্ট' : 'Bulk CSV Import for Books' ?>
            </h2>
            <small style="color:var(--text-muted);">
                <?= $lang === 'bn'
                    ? 'CSV আপলোড করে প্রিভিউ ও যাচাই শেষে ব্যাচে বই ইমপোর্ট করুন। কলাম: folder_path, title_bn, title_en, author, price, is_free, sample_pages, drive_link, description'
                    : 'Upload a CSV, review validation checks, and import books in batches. Columns: folder_path (e.g. Academic/SSC/Bangla), title_bn, title_en, author, price, is_free, sample_pages, drive_link, description.' ?>
            </small>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?= e(url('/admin/import-csv?sample=1')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">
                <?= $lang === 'bn' ? 'স্যাম্পল CSV ডাউনলোড' : 'Download Sample CSV' ?>
            </a>
            <a href="<?= e(url('/admin/pdfs')) ?>" class="bx-btn bx-btn-ghost bx-btn-sm">← <?= $lang === 'bn' ? 'PDF তালিকা' : 'PDF Manager' ?></a>
        </div>
    </div>

    <form method="post" action="<?= e(url('/admin/import-csv')) ?>" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:12px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="csv_preview">
        <div class="bx-grid-2" style="gap:12px;align-items:end;">
            <div>
                <label for="csv-file" style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'CSV ফাইল নির্বাচন করুন *' : 'Choose CSV File (.csv) *' ?></label>
                <input type="file" id="csv-file" name="csv_file" accept=".csv,text/csv" class="bx-input" required>
            </div>
            <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:600;">
                <input type="checkbox" name="auto_create_folders" value="1" checked>
                <?= $lang === 'bn' ? 'অনুপস্থিত ফোল্ডার স্বয়ংক্রিয়ভাবে তৈরি করুন' : 'Automatically create missing folders (folder_path)' ?>
            </label>
        </div>
        <div>
            <button type="submit" class="bx-btn bx-btn-primary">
                <?= $lang === 'bn' ? 'আপলোড ও প্রিভিউ দেখুন' : 'Upload & Validate Preview' ?>
            </button>
        </div>
    </form>
</section>

<?php if ($previewValid): ?>
<section class="bx-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <strong><?= $lang === 'bn' ? 'ইমপোর্ট প্রিভিউ:' : 'Validation Preview:' ?></strong>
            <span class="bx-badge"><?= (int) $previewSummary['total'] ?> total</span>
            <span class="bx-status-pill st-active"><?= (int) $previewSummary['valid'] ?> valid</span>
            <?php if ((int) $previewSummary['errors'] > 0): ?>
                <span class="bx-status-pill st-rejected"><?= (int) $previewSummary['errors'] ?> with errors</span>
            <?php endif; ?>
            <?php if ((int) $previewSummary['new_folders'] > 0): ?>
                <span class="bx-status-pill st-pending"><?= (int) $previewSummary['new_folders'] ?> missing folder row(s)</span>
            <?php endif; ?>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php if ((int) $previewSummary['valid'] > 0): ?>
                <button type="button" id="btn-csv-batch-import" class="bx-btn bx-btn-primary"
                        data-endpoint="<?= e(url('/admin/api/import-csv-batch')) ?>"
                        data-total="<?= (int) $previewSummary['valid'] ?>">
                    <?= $lang === 'bn' ? 'ব্যাচে ইমপোর্ট শুরু করুন' : 'Import Valid Rows in Batches' ?>
                </button>
                <form method="post" action="<?= e(url('/admin/import-csv')) ?>" style="margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="csv_commit_all">
                    <button type="submit" class="bx-btn bx-btn-outline"><?= $lang === 'bn' ? 'একসাথে ইমপোর্ট' : 'Import All Now' ?></button>
                </form>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/admin/import-csv')) ?>" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="csv_clear_preview">
                <button type="submit" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'বাতিল' : 'Clear Preview' ?></button>
            </form>
        </div>
    </div>

    <div id="csv-batch-progress" hidden style="padding:12px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);margin-bottom:14px;">
        <div style="display:flex;justify-content:space-between;font-size:0.88rem;font-weight:700;margin-bottom:6px;">
            <span id="csv-batch-status">Importing batches...</span>
            <span id="csv-batch-pct">0%</span>
        </div>
        <div style="height:10px;border-radius:999px;background:var(--bg);overflow:hidden;border:1px solid var(--border);">
            <div id="csv-batch-fill" style="width:0%;height:100%;background:var(--grad-indigo-teal);transition:width 0.2s ease;"></div>
        </div>
    </div>

    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= $lang === 'bn' ? 'ফোল্ডার পাথ' : 'Folder Path' ?></th>
                    <th><?= $lang === 'bn' ? 'শিরোনাম ও স্লাগ' : 'Title & Slug' ?></th>
                    <th><?= $lang === 'bn' ? 'মূল্য / নমুনা' : 'Price / Sample' ?></th>
                    <th><?= $lang === 'bn' ? 'যাচাই অবস্থা' : 'Validation' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($previewRows as $row): ?>
                    <?php $hasErr = !empty($row['errors']); ?>
                    <tr>
                        <td data-label="Row"><?= (int) $row['line'] ?></td>
                        <td data-label="Folder">
                            <strong><?= e((string) ($row['folder_path'] ?: 'Unassigned')) ?></strong>
                            <?php if (!empty($row['folder_note'])): ?>
                                <div style="font-size:0.78rem;color:var(--gold-text);"><?= e((string) $row['folder_note']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="bx-td-primary">
                            <strong><?= e((string) ($row['title_bn'] ?: $row['title_en'])) ?></strong>
                            <div style="font-size:0.78rem;color:var(--text-muted);"><?= e((string) $row['title_en']) ?> · <code><?= e((string) $row['slug']) ?></code></div>
                        </td>
                        <td data-label="Price">
                            <?= !empty($row['is_free']) ? '<span class="bx-badge bx-badge-teal">Free</span>' : ('৳' . number_format((float) $row['price'], 0)) ?>
                            <small style="color:var(--text-muted);">(<?= (int) $row['sample_pages'] ?>p)</small>
                        </td>
                        <td data-label="Validation">
                            <?php if (!$hasErr): ?>
                                <span class="bx-status-pill st-active"><?= $lang === 'bn' ? 'সঠিক' : 'Valid' ?></span>
                            <?php else: ?>
                                <span class="bx-status-pill st-rejected"><?= $lang === 'bn' ? 'ত্রুটি' : 'Error' ?></span>
                                <div style="font-size:0.78rem;color:var(--rose-text);margin-top:4px;"><?= e(implode('; ', (array) $row['errors'])) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
