<?php
declare(strict_types=1);

$lang = current_lang();
$banners = db_all('SELECT * FROM banners ORDER BY sort_order ASC, id ASC');
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$editBanner = $editId ? db_one('SELECT * FROM banners WHERE id = ?', [$editId]) : null;

$fmtDtLocal = function (?string $dt): string {
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
};
?>

<section class="bx-card" style="margin-bottom:22px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <div>
            <h2 style="margin:0;font-size:1.1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'সক্রিয় ও নির্ধারিত ব্যানার' : 'Banners (Touch/Mouse Reorder)' ?></h2>
            <small style="color:var(--text-muted);"><?= $lang === 'bn' ? 'কোনো সক্রিয় ব্যানার না থাকলে হোমপেজে PDF ও Shopping কার্ড দেখাবে।' : 'Fallback shows PDF & Shopping 3D portals if no active banner is scheduled.' ?></small>
        </div>
        <a href="<?= e(url('/admin/banners#banner-form')) ?>" class="bx-btn bx-btn-primary">+ <?= $lang === 'bn' ? 'নতুন ব্যানার' : 'Add Banner' ?></a>
    </div>

    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th style="width:44px;">⋮⋮</th>
                    <th><?= $lang === 'bn' ? 'ছবি' : 'Preview' ?></th>
                    <th><?= $lang === 'bn' ? 'শিরোনাম ও লিংক' : 'Title & Link' ?></th>
                    <th><?= $lang === 'bn' ? 'সময়সূচি' : 'Schedule' ?></th>
                    <th><?= $lang === 'bn' ? 'অবস্থা' : 'Status' ?></th>
                    <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Actions' ?></th>
                </tr>
            </thead>
            <tbody id="bx-banner-tbody" data-reorder-endpoint="<?= e(url('/admin/api/banner-reorder')) ?>">
                <?php if (!$banners): ?>
                    <tr class="bx-empty-row"><td colspan="6" style="color:var(--text-muted);padding:24px;"><?= $lang === 'bn' ? 'কোনো ব্যানার নেই।' : 'No banners configured.' ?></td></tr>
                <?php else: ?>
                    <?php foreach ($banners as $b): ?>
                        <?php $bid = (int) $b['id']; ?>
                        <tr class="bx-drag-row" draggable="true" data-banner-id="<?= $bid ?>">
                            <td data-label="<?= e($lang === 'bn' ? 'সাজান' : 'Order') ?>">
                                <span class="bx-drag-handle" title="Drag to reorder">⋮⋮</span>
                            </td>
                            <td data-label="<?= e($lang === 'bn' ? 'ছবি' : 'Preview') ?>">
                                <div style="display:flex;gap:8px;align-items:center;">
                                    <img src="<?= e(asset($b['image'])) ?>" alt="" class="bx-thumb-preview" title="Desktop Banner">
                                    <?php if (!empty($b['mobile_image'])): ?>
                                        <img src="<?= e(asset($b['mobile_image'])) ?>" alt="" class="bx-thumb-preview" style="width:42px;height:48px;" title="Mobile Banner">
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="bx-td-primary">
                                <strong style="display:block;overflow-wrap:anywhere;"><?= e($lang === 'bn' ? $b['title_bn'] : $b['title_en']) ?></strong>
                                <span class="bx-ellipsis" style="font-size:0.78rem;color:var(--text-muted);"><?= e($b['title_en']) ?> → <?= e($b['link_url']) ?></span>
                            </td>
                            <td data-label="<?= e($lang === 'bn' ? 'সময়সূচি' : 'Schedule') ?>" style="font-size:0.8rem;color:var(--text-muted);">
                                <?php if ($b['starts_at'] || $b['ends_at']): ?>
                                    <span><?= $b['starts_at'] ? e(date('d M H:i', strtotime($b['starts_at']))) : '∞' ?> → <?= $b['ends_at'] ? e(date('d M H:i', strtotime($b['ends_at']))) : '∞' ?></span>
                                <?php else: ?>
                                    <span><?= $lang === 'bn' ? 'সর্বদা সক্রিয়' : 'Always active' ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?= e($lang === 'bn' ? 'অবস্থা' : 'Status') ?>">
                                <span class="bx-status-pill <?= (int) $b['is_active'] ? 'st-active' : 'st-inactive' ?>">
                                    <?= (int) $b['is_active'] ? ($lang === 'bn' ? 'সক্রিয়' : 'Active') : ($lang === 'bn' ? 'নিষ্ক্রিয়' : 'Inactive') ?>
                                </span>
                            </td>
                            <td class="bx-td-actions">
                                <a href="<?= e(url('/admin/banners?edit=' . $bid . '#banner-form')) ?>" class="bx-btn bx-btn-outline" style="padding:8px 12px;font-size:0.84rem;"><?= $lang === 'bn' ? 'সম্পাদনা' : 'Edit' ?></a>
                                <div class="bx-kebab-wrap">
                                    <button type="button" class="bx-kebab-btn" data-kebab-toggle aria-label="Banner Actions">⋮</button>
                                    <div class="bx-kebab-menu">
                                        <form method="post" action="<?= e(url('/admin/banners')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_banner">
                                            <input type="hidden" name="id" value="<?= $bid ?>">
                                            <button type="submit" class="bx-btn bx-btn-ghost"><?= (int) $b['is_active'] ? ($lang === 'bn' ? 'বন্ধ করুন' : 'Pause Banner') : ($lang === 'bn' ? 'চালু করুন' : 'Activate') ?></button>
                                        </form>
                                        <form method="post" action="<?= e(url('/admin/banners')) ?>" data-confirm-msg="<?= e($lang === 'bn' ? 'এই ব্যানারটি মুছে ফেলবেন?' : 'Delete this banner?') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_banner">
                                            <input type="hidden" name="id" value="<?= $bid ?>">
                                            <button type="submit" class="bx-btn bx-btn-ghost" style="color:var(--rose);">✕ <?= $lang === 'bn' ? 'মুছুন' : 'Delete' ?></button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Add / Edit Banner Form -->
<section class="bx-card" id="banner-form">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <h2 style="margin:0;font-size:1.12rem;font-family:var(--font-serif);">
            <?= $editBanner ? ($lang === 'bn' ? 'ব্যানার সম্পাদনা' : 'Edit Banner') : ($lang === 'bn' ? 'নতুন ব্যানার যুক্ত করুন' : 'Create Banner') ?>
        </h2>
        <?php if ($editBanner): ?>
            <a href="<?= e(url('/admin/banners')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'বাতিল' : 'Reset' ?></a>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= e(url('/admin/banners')) ?>" enctype="multipart/form-data" class="bx-track-dirty">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_banner">
        <?php if ($editBanner): ?>
            <input type="hidden" name="id" value="<?= (int) $editBanner['id'] ?>">
        <?php endif; ?>

        <div class="bx-grid-2" style="gap:12px;margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'শিরোনাম (বাংলা) *' : 'Title (Bangla) *' ?></label>
                <input type="text" name="title_bn" class="bx-input" required value="<?= e((string) ($editBanner['title_bn'] ?? '')) ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'শিরোনাম (ইংরেজি) *' : 'Title (English) *' ?></label>
                <input type="text" name="title_en" class="bx-input" required value="<?= e((string) ($editBanner['title_en'] ?? '')) ?>">
            </div>
        </div>

        <div class="bx-grid-2" style="gap:12px;margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'উপ-শিরোনাম (বাংলা)' : 'Subtitle (Bangla)' ?></label>
                <input type="text" name="subtitle_bn" class="bx-input" value="<?= e((string) ($editBanner['subtitle_bn'] ?? '')) ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'উপ-শিরোনাম (ইংরেজি)' : 'Subtitle (English)' ?></label>
                <input type="text" name="subtitle_en" class="bx-input" value="<?= e((string) ($editBanner['subtitle_en'] ?? '')) ?>">
            </div>
        </div>

        <div class="bx-grid-3" style="gap:12px;margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'বাটন টেক্সট (বাংলা)' : 'CTA Label (Bangla)' ?></label>
                <input type="text" name="cta_bn" class="bx-input" value="<?= e((string) ($editBanner['cta_bn'] ?? 'এখনই দেখুন')) ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'বাটন টেক্সট (ইংরেজি)' : 'CTA Label (English)' ?></label>
                <input type="text" name="cta_en" class="bx-input" value="<?= e((string) ($editBanner['cta_en'] ?? 'Explore Now')) ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'টার্গেট লিংক' : 'Target Link URL' ?></label>
                <input type="text" name="link_url" class="bx-input" value="<?= e((string) ($editBanner['link_url'] ?? '/pdf')) ?>">
            </div>
        </div>

        <div class="bx-grid-3" style="gap:12px;margin-bottom:14px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ক্রম (Sort Order)' : 'Sort Order' ?></label>
                <input type="number" inputmode="numeric" name="sort_order" class="bx-input" value="<?= e((string) ($editBanner['sort_order'] ?? 0)) ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'প্রকাশ শুরুর সময় (Publish At)' : 'Publish At (Scheduled Start)' ?></label>
                <input type="datetime-local" name="publish_at" class="bx-input" value="<?= e($fmtDtLocal($editBanner['publish_at'] ?? $editBanner['starts_at'] ?? null)) ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'প্রকাশ শেষের সময় (Unpublish At)' : 'Unpublish At (Scheduled End)' ?></label>
                <input type="datetime-local" name="unpublish_at" class="bx-input" value="<?= e($fmtDtLocal($editBanner['unpublish_at'] ?? $editBanner['ends_at'] ?? null)) ?>">
            </div>
        </div>

        <div class="bx-grid-2" style="gap:14px;margin-bottom:14px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ডেস্কটপ ব্যানার ছবি (ক্যামেরা/গ্যালারি)' : 'Desktop Image (Camera / Gallery)' ?></label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="bx-input" data-preview-target="banner-desk-preview">
                <?php $deskSrc = !empty($editBanner['image']) ? asset($editBanner['image']) : ''; ?>
                <img id="banner-desk-preview" src="<?= e($deskSrc) ?>" alt="" class="bx-live-preview" style="<?= $deskSrc ? '' : 'display:none;' ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'মোবাইল ব্যানার ছবি (ঐচ্ছিক)' : 'Mobile Image Version (Optional)' ?></label>
                <input type="file" name="mobile_image" accept="image/jpeg,image/png,image/webp" class="bx-input" data-preview-target="banner-mob-preview">
                <?php $mobSrc = !empty($editBanner['mobile_image']) ? asset($editBanner['mobile_image']) : ''; ?>
                <img id="banner-mob-preview" src="<?= e($mobSrc) ?>" alt="" class="bx-live-preview" style="<?= $mobSrc ? '' : 'display:none;' ?>">
                <?php if ($mobSrc): ?>
                    <label style="display:flex;align-items:center;gap:6px;margin-top:6px;min-height:44px;font-size:0.84rem;color:var(--rose);">
                        <input type="checkbox" name="clear_mobile_image" value="1"> <?= $lang === 'bn' ? 'মোবাইল ছবি মুছুন' : 'Remove mobile image' ?>
                    </label>
                <?php endif; ?>
            </div>
        </div>

        <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:600;">
            <input type="checkbox" name="is_active" value="1" <?= !$editBanner || (int) $editBanner['is_active'] ? 'checked' : '' ?>>
            <?= $lang === 'bn' ? 'ব্যানার সক্রিয় রাখুন' : 'Active on Homepage' ?>
        </label>

        <div class="bx-sticky-form-actions">
            <button type="submit" class="bx-btn bx-btn-primary">
                <?= $editBanner ? ($lang === 'bn' ? 'ব্যানার আপডেট করুন' : 'Update Banner') : ($lang === 'bn' ? 'ব্যানার সংরক্ষণ করুন' : 'Save Banner') ?>
            </button>
            <?php if ($editBanner): ?>
                <a href="<?= e(url('/admin/banners')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'বাতিল' : 'Cancel' ?></a>
            <?php endif; ?>
        </div>
    </form>
</section>
