<?php
declare(strict_types=1);

require_once APP_ROOT . '/includes/pdf_proxy.php';

$lang = current_lang();
$itemId = (int) ($_GET['id'] ?? 0);
$item = $itemId > 0 ? db_one('SELECT * FROM items WHERE id = ?', [$itemId]) : null;

if (!$item):
    $itemsList = db_all('SELECT id, title_bn, title_en, is_free, price, total_pages, pages_status, processed_at FROM items ORDER BY id DESC LIMIT 100');
?>
<section class="bx-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <div>
            <h2 style="margin:0;font-size:1.12rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'সুরক্ষিত পৃষ্ঠা প্রস্তুতকরণ' : 'Prepare Protected PDF Pages' ?></h2>
            <small style="color:var(--text-muted);"><?= $lang === 'bn' ? 'পেইড বইয়ের মূল PDF ব্রাউজারে না পাঠিয়ে ওয়েবপি (WebP) সুরক্ষিত পৃষ্ঠা তৈরি করুন।' : 'Render PDF pages in admin browser to protected server WebP images so users never receive raw PDFs.' ?></small>
        </div>
        <a href="<?= e(url('/admin/pdfs')) ?>" class="bx-btn bx-btn-outline">← <?= $lang === 'bn' ? 'PDF তালিকা' : 'Back to PDFs' ?></a>
    </div>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'বই' : 'Book' ?></th>
                    <th><?= $lang === 'bn' ? 'ধরণ' : 'Type' ?></th>
                    <th><?= $lang === 'bn' ? 'পৃষ্ঠা অবস্থা' : 'Pages Status' ?></th>
                    <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Action' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itemsList as $it): $st = (string) ($it['pages_status'] ?? 'unprocessed'); ?>
                <tr>
                    <td class="bx-td-primary"><strong><?= e($lang === 'bn' ? $it['title_bn'] : $it['title_en']) ?></strong></td>
                    <td data-label="Type"><?= (int) $it['is_free'] ? 'Free' : 'Paid (৳' . number_format((float) $it['price'], 0) . ')' ?></td>
                    <td data-label="Status">
                        <span class="bx-status-pill <?= $st === 'ready' ? 'st-active' : 'st-pending' ?>">
                            <?= e(ucfirst($st)) ?><?= $it['total_pages'] ? ' (' . (int) $it['total_pages'] . 'p)' : '' ?>
                        </span>
                    </td>
                    <td class="bx-td-actions">
                        <a href="<?= e(url('/admin/prepare-pages?id=' . (int) $it['id'])) ?>" class="bx-btn bx-btn-primary bx-btn-sm">
                            <?= $lang === 'bn' ? 'প্রস্তুত করুন' : 'Prepare Pages' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php return; endif;

$existingCount = count_prepared_pages((int) $item['id']);
$resumeFrom = ($existingCount > 0 && ($item['pages_status'] ?? '') !== 'ready') ? ($existingCount + 1) : 1;
$hasDriveLink = trim((string) ($item['drive_link'] ?? '')) !== '';
$streamUrl = $hasDriveLink ? url('/admin/api/stream-source?item_id=' . (int) $item['id'] . '&_csrf=' . rawurlencode(csrf_token())) : '';
?>

<section class="bx-card" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <div>
            <span class="bx-badge <?= ($item['pages_status'] ?? '') === 'ready' ? 'bx-badge-success' : 'bx-badge-accent' ?>" id="prep-status-badge" style="margin-bottom:8px;">
                <?= e(strtoupper((string) ($item['pages_status'] ?? 'unprocessed'))) ?>
                <?= $existingCount > 0 ? ' • ' . $existingCount . 'p stored' : '' ?>
            </span>
            <h2 style="margin:0;font-size:1.2rem;font-family:var(--font-serif);">
                <?= e($lang === 'bn' ? $item['title_bn'] : $item['title_en']) ?>
            </h2>
            <small style="color:var(--text-muted);">
                <?= $lang === 'bn' ? 'ব্রাউজারে ১৪০০px প্রস্থে WebP (~৮০% মান) রেন্ডার করে সুরক্ষিত storage/pages/' . (int) $item['id'] . ' ফোল্ডারে সংরক্ষণ করবে। পাঠক কখনও Drive-এ সংযোগ করবে না।' : 'Renders pages at ~1400px wide WebP (quality 80) into protected server storage. Readers never fetch from Google Drive once ready.' ?>
            </small>
        </div>
        <a href="<?= e(url('/admin/pdfs')) ?>" class="bx-btn bx-btn-outline">← <?= $lang === 'bn' ? 'PDF তালিকা' : 'PDF Manager' ?></a>
    </div>

    <div class="bx-grid-2" style="align-items:start;">
        <div>
            <div style="background:var(--bg);border:1px solid var(--border);border-radius:16px;padding:16px;margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;gap:8px;flex-wrap:wrap;">
                    <strong id="prep-counter" style="font-size:0.95rem;">
                        <?= $existingCount > 0 ? ($lang === 'bn' ? "সংরক্ষিত পৃষ্ঠা: {$existingCount}" : "Stored pages on disk: {$existingCount}") : ($lang === 'bn' ? 'প্রস্তুত করার জন্য অপেক্ষমাণ' : 'Ready to start') ?>
                    </strong>
                    <span id="prep-percent" style="font-weight:700;color:var(--primary);">0%</span>
                </div>
                <div style="width:100%;height:12px;border-radius:999px;background:var(--bg-alt);overflow:hidden;border:1px solid var(--border);">
                    <div id="prep-progress-fill" style="width:0%;height:100%;background:var(--grad-indigo-teal);transition:width 0.25s ease;"></div>
                </div>
                <p id="prep-status-text" style="margin:10px 0 0;font-size:0.88rem;color:var(--text-muted);">
                    <?= $hasDriveLink
                        ? ($lang === 'bn' ? 'Drive থেকে স্ট্রিম করে অথবা সরাসরি কম্পিউটারের PDF ফাইল বেছে পৃষ্ঠা তৈরি শুরু করুন।' : 'Stream from Drive OR pick a PDF file directly from your computer (works for 150MB+ files without Drive).')
                        : ($lang === 'bn' ? 'এই বইয়ের কোনো Drive লিংক নেই — নিচ থেকে সরাসরি আপনার কম্পিউটারের PDF ফাইল নির্বাচন করুন।' : 'No Drive URL set — pick a PDF directly from your computer below to render & upload protected pages.') ?>
                </p>
            </div>

            <!-- Local Computer File Fallback (No Drive Needed) -->
            <div style="background:var(--bg-alt);border:1px dashed var(--border);border-radius:14px;padding:14px;margin-bottom:14px;">
                <label for="prep-local-file" style="display:block;font-weight:700;margin-bottom:6px;font-size:0.92rem;">
                    <?= $lang === 'bn' ? 'অথবা কম্পিউটার থেকে সরাসরি PDF নির্বাচন করুন (Drive ছাড়াই)' : 'Pick PDF Directly from Computer (Drive-free fallback for large PDFs)' ?>
                </label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <input type="file" id="prep-local-file" accept=".pdf,application/pdf" class="bx-input" style="flex:1 1 220px;min-width:0;">
                    <button type="button" id="btn-start-local-prepare" class="bx-btn bx-btn-primary">
                        <?= $lang === 'bn' ? 'নির্বাচিত ফাইল থেকে প্রস্তুত করুন' : 'Prepare From Local PDF' ?>
                    </button>
                </div>
                <small style="display:block;margin-top:6px;color:var(--text-muted);">
                    <?= $lang === 'bn' ? 'ফাইলটি আপনার ব্রাউজারেই রেন্ডার হয়ে কেবল পৃষ্ঠার ছবি সার্ভারে আপলোড হবে (সার্ভার মেমোরি বা Drive কোটার ওপর চাপ পড়বে না)।' : 'Processed locally inside your browser — only compressed page images are uploaded to the server.' ?>
                </small>
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php if ($hasDriveLink): ?>
                <button type="button" id="btn-start-prepare" class="bx-btn bx-btn-outline">
                    <?= $resumeFrom > 1 ? ($lang === 'bn' ? "Drive থেকে পৃষ্ঠা {$resumeFrom} থেকে চালু" : "Resume Drive (Page {$resumeFrom})") : ($lang === 'bn' ? 'Drive থেকে প্রস্তুত করুন' : 'Prepare From Drive') ?>
                </button>
                <?php endif; ?>
                <button type="button" id="btn-reprocess-all" class="bx-btn bx-btn-ghost">
                    <?= $lang === 'bn' ? 'পৃষ্ঠা ১ থেকে পুনরায় চালু' : 'Re-process All From Page 1' ?>
                </button>
                <button type="button" id="btn-retry-prepare" class="bx-btn bx-btn-accent" style="display:none;">
                    <?= $lang === 'bn' ? 'আবার চেষ্টা করুন' : 'Retry Failed Page' ?>
                </button>
            </div>
        </div>

        <div style="text-align:center;background:var(--bg);border:1px solid var(--border);border-radius:16px;padding:16px;">
            <div style="font-size:0.78rem;text-transform:uppercase;color:var(--text-muted);font-weight:700;margin-bottom:10px;">
                <?= $lang === 'bn' ? 'লাইভ রেন্ডার প্রিভিউ' : 'Live Page Render Preview' ?>
            </div>
            <canvas id="prep-preview-canvas" style="max-width:220px;width:100%;height:auto;border-radius:10px;border:1px solid var(--border);background:#fff;margin:0 auto;display:block;"></canvas>
        </div>
    </div>
</section>

<script src="<?= e(asset('assets/vendor/pdfjs/pdf.min.js')) ?>"></script>
<script id="bx-prepare-config" type="application/json"><?= json_encode([
    'itemId'        => (int) $item['id'],
    'streamUrl'     => $streamUrl,
    'hasDriveLink'  => $hasDriveLink,
    'uploadUrl'     => url('/admin/api/upload-page'),
    'finalizeUrl'   => url('/admin/api/finalize-pages'),
    'workerUrl'     => asset('assets/vendor/pdfjs/pdf.worker.min.js'),
    'csrfToken'     => csrf_token(),
    'existingCount' => (int) $existingCount,
    'resumeFrom'    => (int) $resumeFrom,
    'pagesStatus'   => (string) ($item['pages_status'] ?? 'unprocessed'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('assets/js/admin_prepare.js')) ?>"></script>
