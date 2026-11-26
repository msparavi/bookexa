<?php
declare(strict_types=1);

$lang = current_lang();
$queueItems = db_all(
    "SELECT id, title_bn, title_en, slug, cover, pages_status, processing_status, drive_link
     FROM items
     WHERE pages_status <> 'ready' AND TRIM(COALESCE(drive_link, '')) <> ''
     ORDER BY id ASC
     LIMIT 200"
);
$missingDriveCnt = (int) (db_one("SELECT COUNT(*) AS c FROM items WHERE pages_status <> 'ready' AND TRIM(COALESCE(drive_link, '')) = ''")['c'] ?? 0);
$queuePayload = array_map(static fn(array $r): array => [
    'id'        => (int) $r['id'],
    'title'     => $lang === 'bn' ? ($r['title_bn'] ?: $r['title_en']) : ($r['title_en'] ?: $r['title_bn']),
    'hasCover'  => trim((string) ($r['cover'] ?? '')) !== '' && !str_ends_with((string) $r['cover'], 'cover-bangla.svg'),
    'streamUrl' => url('/admin/api/stream-source?item_id=' . (int) $r['id'] . '&_csrf=' . rawurlencode(csrf_token())),
], $queueItems);
?>
<section class="bx-card" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <div>
            <h2 style="margin:0;font-size:1.15rem;font-family:var(--font-serif);">
                <?= $lang === 'bn' ? 'অপ্রস্তুত সব বই প্রস্তুত কিউ' : 'Prepare All Unprocessed Books Queue' ?>
            </h2>
            <small style="color:var(--text-muted);">
                <?= $lang === 'bn'
                    ? 'Drive লিংকযুক্ত অপ্রস্তুত বইগুলো ব্রাউজারে একে একে রেন্ডার, সূচিপত্র ও অটো-কভার (৩:৪) সহ সংরক্ষণ করে।'
                    : 'Sequentially renders unprocessed Drive-linked books in the admin browser, generates missing 3:4 covers from Page 1, extracts TOC & search index, and finalizes.' ?>
            </small>
        </div>
        <a href="<?= e(url('/admin/pdfs')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">← <?= $lang === 'bn' ? 'PDF তালিকা' : 'PDF Manager' ?></a>
    </div>

    <?php if ($missingDriveCnt > 0): ?>
        <div class="bx-alert bx-alert-info" style="margin-bottom:14px;">
            <?= $lang === 'bn'
                ? "{$missingDriveCnt}টি অপ্রস্তুত বইয়ে Drive লিংক নেই — সেগুলো প্রতিটি বইয়ের Prepare Pages থেকে স্থানীয় PDF ফাইল বেছে তৈরি করতে হবে।"
                : "{$missingDriveCnt} unprocessed book(s) have no Drive link; open Prepare Pages on those items to upload from your computer." ?>
        </div>
    <?php endif; ?>

    <div style="background:var(--bg);border:1px solid var(--border);border-radius:14px;padding:14px;margin-bottom:14px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
            <strong id="queue-overall-label">
                <?= $lang === 'bn' ? 'অপেক্ষমাণ বই: ' : 'Pending books: ' ?><?= count($queueItems) ?>
            </strong>
            <span id="queue-overall-pct" style="font-weight:700;color:var(--primary);">0%</span>
        </div>
        <div style="height:10px;border-radius:999px;background:var(--bg-alt);overflow:hidden;border:1px solid var(--border);margin-bottom:10px;">
            <div id="queue-overall-fill" style="width:0%;height:100%;background:var(--grad-indigo-teal);transition:width 0.25s ease;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
            <small id="queue-book-label" style="color:var(--text-muted);"><?= $lang === 'bn' ? 'প্রস্তুত শুরু করতে Start Queue চাপুন।' : 'Press Start Queue to begin batch rendering.' ?></small>
            <small id="queue-book-pct" style="font-weight:700;">0%</small>
        </div>
        <div style="height:8px;border-radius:999px;background:var(--bg-alt);overflow:hidden;border:1px solid var(--border);">
            <div id="queue-book-fill" style="width:0%;height:100%;background:var(--teal);transition:width 0.2s ease;"></div>
        </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <button type="button" id="btn-queue-start" class="bx-btn bx-btn-primary" <?= empty($queueItems) ? 'disabled' : '' ?>>
            <?= $lang === 'bn' ? 'কিউ শুরু করুন' : 'Start Queue' ?>
        </button>
        <button type="button" id="btn-queue-pause" class="bx-btn bx-btn-outline" disabled>
            <?= $lang === 'bn' ? 'বিরতি / পুনরায় শুরু' : 'Pause' ?>
        </button>
    </div>

    <pre id="queue-log" style="background:var(--bg-alt);border:1px solid var(--border);border-radius:12px;padding:12px;max-height:220px;overflow-y:auto;font-size:0.82rem;margin:0;white-space:pre-wrap;"></pre>
</section>

<section class="bx-card">
    <h3 style="margin:0 0 12px;font-size:1.05rem;font-family:var(--font-serif);">
        <?= $lang === 'bn' ? 'অপ্রস্তুত বইয়ের তালিকা' : 'Queued Books' ?> (<?= count($queueItems) ?>)
    </h3>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?= $lang === 'bn' ? 'শিরোনাম' : 'Title' ?></th>
                    <th><?= $lang === 'bn' ? 'অবস্থা' : 'Status' ?></th>
                    <th><?= $lang === 'bn' ? 'একক প্রস্তুতি' : 'Individual' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$queueItems): ?>
                    <tr class="bx-empty-row"><td colspan="4" style="color:var(--text-muted);padding:20px;"><?= $lang === 'bn' ? 'সব Drive বই ইতিমধ্যে প্রস্তুত!' : 'All Drive-linked books are already prepared!' ?></td></tr>
                <?php else: foreach ($queueItems as $qi): ?>
                    <tr id="queue-row-<?= (int) $qi['id'] ?>">
                        <td data-label="ID">#<?= (int) $qi['id'] ?></td>
                        <td class="bx-td-primary"><strong><?= e($lang === 'bn' ? $qi['title_bn'] : $qi['title_en']) ?></strong></td>
                        <td data-label="Status"><span class="bx-status-pill st-pending" data-queue-status><?= e(ucfirst((string) $qi['pages_status'])) ?></span></td>
                        <td class="bx-td-actions">
                            <a href="<?= e(url('/admin/prepare-pages?id=' . (int) $qi['id'])) ?>" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'খুলুন' : 'Open Tool' ?></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script src="<?= e(asset('assets/vendor/pdfjs/pdf.min.js')) ?>"></script>
<script id="bx-queue-config" type="application/json"><?= json_encode([
    'items'         => $queuePayload,
    'uploadUrl'     => url('/admin/api/upload-page'),
    'finalizeUrl'   => url('/admin/api/finalize-pages'),
    'discardUrl'    => url('/admin/api/discard-staging'),
    'autoCoverUrl'  => url('/admin/api/upload-auto-cover'),
    'workerUrl'     => asset('assets/vendor/pdfjs/pdf.worker.min.js'),
    'csrfToken'     => csrf_token(),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('assets/js/admin_prepare.js')) ?>"></script>
