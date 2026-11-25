<?php
declare(strict_types=1);

$lang = current_lang();
$rows = db_all('SELECT setting_key, setting_value FROM settings');
$cfg = [];
foreach ($rows as $r) {
    $cfg[$r['setting_key']] = $r['setting_value'];
}
?>

<form method="post" action="<?= e(url('/admin/settings')) ?>" enctype="multipart/form-data" class="bx-track-dirty">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_settings">

    <div class="bx-grid-2" style="align-items:start;margin-bottom:20px;">
        <!-- General & Branding -->
        <section class="bx-card">
            <h2 style="margin:0 0 14px;font-size:1.12rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'সাইট পরিচিতি ও ভাষা' : 'Site Identity & System' ?></h2>

            <div class="bx-field">
                <label><?= $lang === 'bn' ? 'সাইটের নাম' : 'Site Name' ?></label>
                <input type="text" name="site_name" class="bx-input" required value="<?= e($cfg['site_name'] ?? 'Bookexa') ?>">
            </div>

            <div class="bx-grid-2" style="gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ডিফল্ট ভাষা' : 'Default Language' ?></label>
                    <select name="default_language" class="bx-input">
                        <option value="bn" <?= ($cfg['default_language'] ?? 'bn') === 'bn' ? 'selected' : '' ?>>বাংলা (Bangla)</option>
                        <option value="en" <?= ($cfg['default_language'] ?? 'bn') === 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                </div>
                <div style="display:flex;flex-direction:column;justify-content:flex-end;gap:4px;">
                    <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:700;color:var(--rose-text);">
                        <input type="checkbox" name="maintenance_mode" value="1" <?= ($cfg['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <?= $lang === 'bn' ? 'মেইনটেন্যান্স মোড চালু' : 'Maintenance Mode' ?>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:700;color:var(--primary);">
                        <input type="checkbox" name="shop_enabled" value="1" <?= ($cfg['shop_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <?= $lang === 'bn' ? 'শপ মডিউল দেখান' : 'Enable Shop Links' ?>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:600;">
                        <input type="checkbox" name="free_pdfjs_fallback" value="1" <?= ($cfg['free_pdfjs_fallback'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <?= $lang === 'bn' ? 'শুধুমাত্র ফ্রি বইয়ের জন্য PDF.js ফলব্যাক' : 'Legacy PDF.js fallback (Free items only)' ?>
                    </label>
                </div>
            </div>

            <div class="bx-grid-2" style="gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'সাইট লোগো' : 'Site Logo (Camera / Gallery)' ?></label>
                    <input type="file" name="site_logo" accept="image/*" class="bx-input" data-preview-target="settings-logo-preview">
                    <?php $logoSrc = !empty($cfg['site_logo']) ? asset($cfg['site_logo']) : asset('assets/img/logo.svg'); ?>
                    <img id="settings-logo-preview" src="<?= e($logoSrc) ?>" alt="Logo" class="bx-live-preview" style="max-height:58px;">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ফেভিকন' : 'Site Favicon (PNG/WebP/ICO)' ?></label>
                    <input type="file" name="site_favicon" accept="image/*,.ico" class="bx-input" data-preview-target="settings-fav-preview">
                    <?php $favSrc = !empty($cfg['site_favicon']) ? asset($cfg['site_favicon']) : asset('assets/img/favicon.svg'); ?>
                    <img id="settings-fav-preview" src="<?= e($favSrc) ?>" alt="Favicon" class="bx-live-preview" style="width:48px;height:48px;">
                </div>
            </div>

            <div class="bx-grid-2" style="gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'যোগাযোগ ইমেইল' : 'Contact Email' ?></label>
                    <input type="email" name="contact_email" class="bx-input" value="<?= e($cfg['contact_email'] ?? '') ?>">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'যোগাযোগ ফোন' : 'Contact Phone' ?></label>
                    <input type="tel" name="contact_phone" class="bx-input" value="<?= e($cfg['contact_phone'] ?? '') ?>">
                </div>
            </div>

            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ঠিকানা' : 'Contact Address' ?></label>
                <input type="text" name="contact_address" class="bx-input" value="<?= e($cfg['contact_address'] ?? '') ?>">
            </div>
        </section>

        <!-- Manual Payment Gateway Settings -->
        <section class="bx-card">
            <h2 style="margin:0 0 14px;font-size:1.12rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'ম্যানুয়াল পেমেন্ট নম্বর ও নির্দেশনা' : 'Manual Payment Settings' ?></h2>

            <div class="bx-grid-3" style="gap:10px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">bKash</label>
                    <input type="tel" name="bkash_number" class="bx-input" value="<?= e($cfg['bkash_number'] ?? '') ?>">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Nagad</label>
                    <input type="tel" name="nagad_number" class="bx-input" value="<?= e($cfg['nagad_number'] ?? '') ?>">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Rocket</label>
                    <input type="tel" name="rocket_number" class="bx-input" value="<?= e($cfg['rocket_number'] ?? '') ?>">
                </div>
            </div>

            <div class="bx-field">
                <label><?= $lang === 'bn' ? 'পেমেন্ট নির্দেশনা (বাংলা)' : 'Payment Instructions (Bangla)' ?></label>
                <textarea name="payment_instructions_bn" class="bx-input" rows="3"><?= e($cfg['payment_instructions_bn'] ?? '') ?></textarea>
            </div>

            <div class="bx-field">
                <label><?= $lang === 'bn' ? 'পেমেন্ট নির্দেশনা (ইংরেজি)' : 'Payment Instructions (English)' ?></label>
                <textarea name="payment_instructions_en" class="bx-input" rows="3"><?= e($cfg['payment_instructions_en'] ?? '') ?></textarea>
            </div>
        </section>
    </div>

    <!-- Built-in SMTP Email Settings + Send Test Email -->
    <section class="bx-card" style="margin-bottom:20px;">
        <h2 style="margin:0 0 14px;font-size:1.12rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'ইমেইল (SMTP) সেটিংস' : 'SMTP Email Settings (Built-in Client)' ?></h2>
        <div class="bx-grid-3" style="margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'SMTP হোস্ট' : 'SMTP Host' ?></label>
                <input type="text" id="smtp_host" name="smtp_host" class="bx-input" placeholder="smtp.example.com" value="<?= e($cfg['smtp_host'] ?? '') ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'পোর্ট' : 'Port' ?></label>
                <input type="number" id="smtp_port" name="smtp_port" class="bx-input" placeholder="587" value="<?= e($cfg['smtp_port'] ?? '587') ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'এনক্রিপশন' : 'Encryption' ?></label>
                <select id="smtp_encryption" name="smtp_encryption" class="bx-input">
                    <option value="tls" <?= ($cfg['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option>
                    <option value="ssl" <?= ($cfg['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL / SMTPS (465)</option>
                    <option value="none" <?= ($cfg['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None (Plain)</option>
                </select>
            </div>
        </div>
        <div class="bx-grid-2" style="margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'SMTP ইউজারনেম' : 'SMTP User' ?></label>
                <input type="text" id="smtp_user" name="smtp_user" class="bx-input" autocomplete="off" value="<?= e($cfg['smtp_user'] ?? '') ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'SMTP পাসওয়ার্ড' : 'SMTP Password' ?></label>
                <input type="password" id="smtp_pass" name="smtp_pass" class="bx-input" autocomplete="new-password" placeholder="<?= !empty($cfg['smtp_pass']) ? ($lang === 'bn' ? 'সংরক্ষিত আছে (পরিবর্তন করতে লিখুন)' : 'Saved — leave blank to keep') : ($lang === 'bn' ? 'পাসওয়ার্ড দিন' : 'Enter SMTP password') ?>">
            </div>
        </div>
        <div class="bx-grid-2" style="margin-bottom:14px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'প্রেরকের নাম (From Name)' : 'From Name' ?></label>
                <input type="text" id="smtp_from_name" name="smtp_from_name" class="bx-input" value="<?= e($cfg['smtp_from_name'] ?? 'Bookexa') ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'প্রেরকের ইমেইল (From Address)' : 'From Address' ?></label>
                <input type="email" id="smtp_from_email" name="smtp_from_email" class="bx-input" value="<?= e($cfg['smtp_from_email'] ?? 'no-reply@bookexa.com') ?>">
            </div>
        </div>

        <div style="padding:12px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);margin-bottom:16px;">
            <label for="smtp_test_to" style="display:block;font-weight:600;margin-bottom:6px;"><?= $lang === 'bn' ? 'টেস্ট ইমেইল পাঠিয়ে পরীক্ষা করুন' : 'Test SMTP Configuration' ?></label>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <input type="email" id="smtp_test_to" class="bx-input" style="flex:1 1 220px;min-width:0;" placeholder="recipient@example.com" value="<?= e($admin['email'] ?? '') ?>">
                <button type="button" id="btn-send-test-smtp" class="bx-btn bx-btn-outline" data-endpoint="<?= e(url('/admin/api/test-smtp')) ?>">
                    <?= $lang === 'bn' ? 'টেস্ট ইমেইল পাঠান' : 'Send test email' ?>
                </button>
            </div>
            <small id="smtp-test-result" style="display:block;margin-top:6px;font-weight:600;"></small>
        </div>

        <div style="padding:12px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);">
            <label for="google_drive_api_key" style="display:block;font-weight:600;margin-bottom:4px;">
                <?= $lang === 'bn' ? 'Google Drive API Key (ঐচ্ছিক — বড় বা কোটা-সুরক্ষিত ফাইলের জন্য)' : 'Google Drive API Key (Optional — Official Drive v3 Stream Mode)' ?>
            </label>
            <input type="password" id="google_drive_api_key" name="google_drive_api_key" class="bx-input" autocomplete="new-password" placeholder="<?= !empty($cfg['google_drive_api_key']) ? ($lang === 'bn' ? 'সংরক্ষিত আছে (পরিবর্তন করতে নতুন কী লিখুন)' : 'Saved server-side — leave blank to keep current key') : ($lang === 'bn' ? 'AIza... (সার্ভারে সম্পূর্ণ গোপন থাকবে)' : 'AIza... (stored & used server-side only, never exposed)') ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:6px;">
                <small style="color:var(--text-muted);"><?= $lang === 'bn' ? 'কী দেওয়া থাকলে সার্ভার প্রথমে Google Drive API v3 দিয়ে স্ট্রিম করবে, নাহলে সাধারণ ডাউনলোড পদ্ধতি ব্যবহার করবে।' : 'When set, server tries Google Drive API v3 (supportsAllDrives=true) before falling back to direct download streaming.' ?></small>
                <?php if (!empty($cfg['google_drive_api_key'])): ?>
                    <label style="display:inline-flex;align-items:center;gap:6px;font-size:0.85rem;color:var(--rose-text);font-weight:600;">
                        <input type="checkbox" name="clear_google_drive_api_key" value="1">
                        <?= $lang === 'bn' ? 'সংরক্ষিত API Key মুছুন' : 'Clear saved API key' ?>
                    </label>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Legal Pages Content (Bangla & English) -->
    <section class="bx-card" style="margin-bottom:20px;">
        <h2 style="margin:0 0 14px;font-size:1.12rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'আইনি পাতাসমূহ (বাংলা ও ইংরেজি)' : 'Legal Pages (Bangla & English)' ?></h2>
        <div class="bx-grid-2" style="margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Terms of Use (Bangla)</label>
                <textarea name="legal_terms_bn" class="bx-input" rows="4"><?= e($cfg['legal_terms_bn'] ?? '') ?></textarea>
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Terms of Use (English)</label>
                <textarea name="legal_terms_en" class="bx-input" rows="4"><?= e($cfg['legal_terms_en'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="bx-grid-2" style="margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Privacy Policy (Bangla)</label>
                <textarea name="legal_privacy_bn" class="bx-input" rows="4"><?= e($cfg['legal_privacy_bn'] ?? '') ?></textarea>
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Privacy Policy (English)</label>
                <textarea name="legal_privacy_en" class="bx-input" rows="4"><?= e($cfg['legal_privacy_en'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="bx-grid-2" style="margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Refund Policy (Bangla)</label>
                <textarea name="legal_refund_bn" class="bx-input" rows="4"><?= e($cfg['legal_refund_bn'] ?? '') ?></textarea>
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Refund Policy (English)</label>
                <textarea name="legal_refund_en" class="bx-input" rows="4"><?= e($cfg['legal_refund_en'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="bx-grid-2">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Copyright &amp; Watermark Notice (Bangla)</label>
                <textarea name="legal_copyright_bn" class="bx-input" rows="3"><?= e($cfg['legal_copyright_bn'] ?? '') ?></textarea>
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Copyright &amp; Watermark Notice (English)</label>
                <textarea name="legal_copyright_en" class="bx-input" rows="3"><?= e($cfg['legal_copyright_en'] ?? '') ?></textarea>
            </div>
        </div>
    </section>

    <div class="bx-sticky-form-actions">
        <button type="submit" class="bx-btn bx-btn-primary" style="width:100%;">
            <?= $lang === 'bn' ? 'সকল সেটিংস সংরক্ষণ করুন' : 'Save All Settings' ?>
        </button>
    </div>
</form>

<?php if (($admin['role'] ?? '') === 'superadmin'): ?>
<section class="bx-card" style="margin-top:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0 0 4px;font-size:1.1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'ডাটাবেস ব্যাকআপ (.sql)' : 'Database Backup (.sql)' ?></h2>
            <small style="color:var(--text-muted);"><?= $lang === 'bn' ? 'সম্পূর্ণ MySQL স্কিমা ও ডেটার .sql ব্যাকআপ ডাউনলোড করুন।' : 'Download a full SQL dump of all tables and records for safekeeping before updates.' ?></small>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <a href="<?= e(url('/admin/media')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'মিডিয়া, CDN ও সাইট প্যাকেজ' : 'Media, CDN & Package Export' ?></a>
            <form method="post" action="<?= e(url('/admin/settings')) ?>" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="download_db_backup">
                <button type="submit" class="bx-btn bx-btn-outline"><?= $lang === 'bn' ? 'ব্যাকআপ ডাউনলোড' : 'Backup database' ?></button>
            </form>
        </div>
    </div>
</section>
<?php endif; ?>
