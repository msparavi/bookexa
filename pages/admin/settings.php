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
                    <input type="file" name="site_logo" accept="image/jpeg,image/png,image/webp" class="bx-input" data-preview-target="settings-logo-preview">
                    <?php $logoSrc = !empty($cfg['site_logo']) ? asset($cfg['site_logo']) : asset('assets/img/logo.svg'); ?>
                    <img id="settings-logo-preview" src="<?= e($logoSrc) ?>" alt="Logo" class="bx-live-preview" style="max-height:58px;">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ফেভিকন' : 'Site Favicon (PNG/WebP/ICO)' ?></label>
                    <input type="file" name="site_favicon" accept="image/jpeg,image/png,image/webp,.ico" class="bx-input" data-preview-target="settings-fav-preview">
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

            <div style="margin-top:12px;padding:10px 12px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                <label style="display:flex;align-items:center;gap:8px;min-height:40px;font-weight:700;color:var(--gold-text);">
                    <input type="checkbox" name="demo_mode" value="1" <?= ($cfg['demo_mode'] ?? '0') === '1' || !empty(app_config('demo_mode', false)) ? 'checked' : '' ?>>
                    <?= $lang === 'bn' ? 'ডেমো মোড চালু রাখুন (ডিলিট/সিকিউরিটি পরিবর্তন বন্ধ থাকবে)' : 'Enable Demo Mode (disables destructive actions & protects secrets)' ?>
                </label>
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

            <div class="bx-field">
                <label for="pending_order_expire_days"><?= $lang === 'bn' ? 'অপেক্ষমাণ অর্ডার স্বয়ংক্রিয় মেয়াদোত্তীর্ণ হবে (দিন পর)' : 'Auto-expire pending orders after N days' ?></label>
                <input type="number" id="pending_order_expire_days" name="pending_order_expire_days" min="1" max="365" class="bx-input" style="max-width:200px;" value="<?= e((string) ($cfg['pending_order_expire_days'] ?? '3')) ?>">
                <small style="display:block;color:var(--text-muted);margin-top:4px;"><?= $lang === 'bn' ? 'ডিফল্ট ৩ দিন। মেয়াদ শেষে কুপন পুনরায় মুক্ত হয় ও ব্যবহারকারী নোটিফিকেশন পান।' : 'Default 3 days. Expired orders release reserved coupons automatically and notify customers.' ?></small>
            </div>
        </section>
    </div>

    <!-- Optional Automatic Payment Gateway Adapter (Disabled by Default) -->
    <section class="bx-card" style="margin-bottom:20px;">
        <h2 style="margin:0 0 8px;font-size:1.12rem;font-family:var(--font-serif);">
            <?= $lang === 'bn' ? 'স্বয়ংক্রিয় বাংলাদেশি পেমেন্ট গেটওয়ে (ঐচ্ছিক)' : 'Automatic Payment Gateway Adapter (Optional)' ?>
        </h2>
        <small style="display:block;color:var(--text-muted);margin-bottom:14px;">
            <?= $lang === 'bn'
                ? 'ডিফল্টভাবে বন্ধ থাকে যাতে বর্তমান ম্যানুয়াল bKash/Nagad/Rocket প্রক্রিয়া অপরিবর্তিত থাকে। প্রয়োজনে পরবর্তীতে চালু করুন।'
                : 'Disabled by default so existing manual checkout stays active. Enable when connecting bKash Tokenized, aamarPay, or UddoktaPay.' ?>
        </small>

        <div style="display:flex;gap:18px;flex-wrap:wrap;margin-bottom:14px;">
            <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:700;">
                <input type="checkbox" name="payment_gateway_enabled" value="1" <?= ($cfg['payment_gateway_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                <span><?= $lang === 'bn' ? 'স্বয়ংক্রিয় গেটওয়ে চালু করুন' : 'Enable Automatic Payment Gateway' ?></span>
            </label>
            <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:600;">
                <input type="checkbox" name="payment_gateway_sandbox" value="1" <?= ($cfg['payment_gateway_sandbox'] ?? '1') === '1' ? 'checked' : '' ?>>
                <span><?= $lang === 'bn' ? 'স্যান্ডবক্স টেস্ট মোড চালু রাখুন' : 'Sandbox Test Mode' ?></span>
            </label>
        </div>

        <div class="bx-grid-2" style="margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'গেটওয়ে ড্রাইভার' : 'Gateway Driver' ?></label>
                <?php $gwDrv = (string) ($cfg['payment_gateway_driver'] ?? 'manual'); ?>
                <select name="payment_gateway_driver" class="bx-input">
                    <option value="manual" <?= $gwDrv === 'manual' ? 'selected' : '' ?>>Manual (bKash / Nagad / Rocket)</option>
                    <option value="bkash_tokenized" <?= $gwDrv === 'bkash_tokenized' ? 'selected' : '' ?>>bKash Tokenized Checkout</option>
                    <option value="aamarpay" <?= $gwDrv === 'aamarpay' ? 'selected' : '' ?>>aamarPay</option>
                    <option value="uddoktapay" <?= $gwDrv === 'uddoktapay' ? 'selected' : '' ?>>UddoktaPay</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'API বেস URL' : 'Gateway API Base URL' ?></label>
                <input type="url" name="payment_gateway_base_url" class="bx-input" placeholder="https://sandbox.gateway.example/api" value="<?= e($cfg['payment_gateway_base_url'] ?? '') ?>">
            </div>
        </div>

        <div class="bx-grid-3" style="margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">API Key / Store ID</label>
                <input type="text" name="payment_gateway_api_key" class="bx-input" autocomplete="off" value="<?= e($cfg['payment_gateway_api_key'] ?? '') ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">API Secret</label>
                <input type="password" name="payment_gateway_api_secret" class="bx-input" autocomplete="new-password" placeholder="<?= !empty($cfg['payment_gateway_api_secret']) ? 'Saved — leave blank to keep' : 'Enter API Secret' ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Webhook Signing Secret</label>
                <input type="password" name="payment_gateway_webhook_secret" class="bx-input" autocomplete="new-password" placeholder="<?= !empty($cfg['payment_gateway_webhook_secret']) ? 'Saved — leave blank to keep' : 'Enter Webhook Secret' ?>">
            </div>
        </div>

        <div style="padding:10px 12px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);font-size:0.84rem;">
            <strong>Webhook Callback URL:</strong> <code><?= e(url('/api/payment-webhook')) ?></code>
        </div>
    </section>

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

        <div class="bx-grid-2" style="margin-bottom:14px;">
            <div>
                <label for="reader_daily_quota_mult" style="display:block;font-weight:600;margin-bottom:4px;">
                    <?= $lang === 'bn' ? 'দৈনিক পাঠ সীমা গুণিতক (প্রতি বইয়ের মোট পৃষ্ঠার × গুণ)' : 'Daily Reader Quota Multiplier (× book total pages per user/day)' ?>
                </label>
                <input type="number" id="reader_daily_quota_mult" name="reader_daily_quota_mult" min="0" max="100" class="bx-input" style="max-width:220px;" value="<?= e((string) ($cfg['reader_daily_quota_mult'] ?? '3')) ?>">
                <small style="display:block;color:var(--text-muted);margin-top:4px;">
                    <?= $lang === 'bn'
                        ? 'ডিফল্ট ৩ (যেমন: ১০০ পৃষ্ঠার বই দিনে সর্বোচ্চ ৩০০ পৃষ্ঠা রেন্ডার করা যাবে)। ০ দিলে সীমাহীন।'
                        : 'Default 3 (e.g. a 100-page book allows up to 300 protected page fetches per user per day). Set 0 for unlimited.' ?>
                </small>
            </div>
            <div>
                <label for="max_devices_per_account" style="display:block;font-weight:600;margin-bottom:4px;">
                    <?= $lang === 'bn' ? 'সর্বোচ্চ সক্রিয় ডিভাইস প্রতি অ্যাকাউন্ট' : 'Max Logged-In Devices per Account' ?>
                </label>
                <input type="number" id="max_devices_per_account" name="max_devices_per_account" min="1" max="10" class="bx-input" style="max-width:220px;" value="<?= e((string) ($cfg['max_devices_per_account'] ?? '2')) ?>">
                <small style="display:block;color:var(--text-muted);margin-top:4px;">
                    <?= $lang === 'bn'
                        ? 'ডিফল্ট ২টি ডিভাইস একসাথে লগইন থাকবে (তবে রিডার একসাথে ১টি সেশনেই চলবে)।'
                        : 'Default 2 logged-in browsers allowed without signing each other out (1 concurrent reader session still enforced).' ?>
                </small>
            </div>
        </div>

        <div class="bx-grid-2" style="margin-bottom:14px;">
            <div>
                <label for="max_concurrent_page_renders" style="display:block;font-weight:600;margin-bottom:4px;">
                    <?= $lang === 'bn' ? 'সর্বোচ্চ যুগপৎ পৃষ্ঠা রেন্ডার (/api/page)' : 'Max Concurrent Page Renders (/api/page Busy Limit)' ?>
                </label>
                <input type="number" id="max_concurrent_page_renders" name="max_concurrent_page_renders" min="1" max="64" class="bx-input" style="max-width:220px;" value="<?= e((string) ($cfg['max_concurrent_page_renders'] ?? '8')) ?>">
                <small style="display:block;color:var(--text-muted);margin-top:4px;">
                    <?= $lang === 'bn'
                        ? 'শেয়ার্ড হোস্টিং CPU বাঁচাতে একসাথে কতটি পৃষ্ঠা রেন্ডার হবে (বেশি হলে 503 Retry-After দেবে)।'
                        : 'Protects shared hosting CPU by returning HTTP 503 + Retry-After when active GD renders exceed this cap (default 8).' ?>
                </small>
            </div>
            <div style="display:flex;align-items:center;">
                <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:600;">
                    <input type="checkbox" name="reader_mobile_lightweight" value="1" <?= ($cfg['reader_mobile_lightweight'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span><?= $lang === 'bn' ? 'ফোনে স্বয়ংক্রিয় ১০০০px হালকা পৃষ্ঠা ছবি পাঠান' : 'Enable lightweight 1000px phone page images by default' ?></span>
                </label>
            </div>
        </div>

        <div style="padding:12px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);margin-bottom:16px;">
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

        <!-- SMS OTP Gateway Adapter (Disabled by default) -->
        <div style="padding:14px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
                <strong style="font-size:0.96rem;"><?= $lang === 'bn' ? 'SMS OTP গেটওয়ে অ্যাডাপ্টার (ঐচ্ছিক)' : 'SMS OTP Gateway Adapter (Optional, Disabled by Default)' ?></strong>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <label style="display:inline-flex;align-items:center;gap:6px;font-weight:600;font-size:0.86rem;">
                        <input type="checkbox" name="sms_gateway_enabled" value="1" <?= ($cfg['sms_gateway_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <?= $lang === 'bn' ? 'OTP লগইন চালু করুন' : 'Enable SMS OTP Login' ?>
                    </label>
                    <label style="display:inline-flex;align-items:center;gap:6px;font-weight:600;font-size:0.86rem;">
                        <input type="checkbox" name="sms_gateway_sandbox" value="1" <?= ($cfg['sms_gateway_sandbox'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <?= $lang === 'bn' ? 'স্যান্ডবক্স মোড' : 'Sandbox Mode' ?>
                    </label>
                </div>
            </div>
            <div class="bx-grid-2" style="margin-bottom:10px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.85rem;">Driver</label>
                    <select name="sms_gateway_driver" class="bx-input">
                        <option value="none" <?= ($cfg['sms_gateway_driver'] ?? 'none') === 'none' ? 'selected' : '' ?>>Disabled / None</option>
                        <option value="generic" <?= ($cfg['sms_gateway_driver'] ?? '') === 'generic' ? 'selected' : '' ?>>Generic HTTP SMS API (SSLWireless / BulkSMSBD / Sandbox)</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.85rem;">Sender ID / Masking</label>
                    <input type="text" name="sms_gateway_sender_id" class="bx-input" value="<?= e((string) ($cfg['sms_gateway_sender_id'] ?? 'Bookexa')) ?>">
                </div>
            </div>
            <div class="bx-grid-2">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.85rem;">SMS HTTP API Endpoint URL</label>
                    <input type="url" name="sms_gateway_api_url" class="bx-input" placeholder="https://sms.provider.com/api/v1/send" value="<?= e((string) ($cfg['sms_gateway_api_url'] ?? '')) ?>">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.85rem;">SMS API Key / Token</label>
                    <input type="password" name="sms_gateway_api_key" class="bx-input" autocomplete="new-password" placeholder="<?= !empty($cfg['sms_gateway_api_key']) ? 'Saved — leave blank to keep' : 'Enter API key' ?>">
                </div>
            </div>
            <div style="margin-top:10px;max-width:260px;">
                <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.85rem;">
                    <?= $lang === 'bn' ? 'দৈনিক সর্বোচ্চ SMS বাজেট (গ্লোবাল)' : 'Global Daily SMS Budget' ?>
                </label>
                <input type="number" name="sms_daily_budget" min="0" max="1000000" class="bx-input" value="<?= e((string) ($cfg['sms_daily_budget'] ?? '200')) ?>">
            </div>
        </div>
    </section>

    <!-- White-Label Branding, Colors, Fonts, Footer & Social Links -->
    <section class="bx-card" style="margin-bottom:20px;">
        <h2 style="margin:0 0 8px;font-size:1.12rem;font-family:var(--font-serif);">
            <?= $lang === 'bn' ? 'হোয়াইট-লেবেল ব্র্যান্ডিং, রঙ, ফন্ট ও সোশ্যাল লিংক' : 'White-Label Theme, Colors, Typography, Footer & Social Links' ?>
        </h2>
        <p class="bx-muted" style="margin:0 0 14px;font-size:0.86rem;">
            <?= $lang === 'bn' ? 'কোনো কোড পরিবর্তন না করেই সাইটের রঙ, ফন্ট, ফুটার টেক্সট ও সোশ্যাল লিংক কাস্টমাইজ করুন।' : 'Customize colors, offline font pairing, footer text, and social channels cleanly with zero code edits.' ?>
        </p>

        <div class="bx-grid-3" style="gap:12px;margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'প্রাইমারি রঙ' : 'Primary Brand Color' ?></label>
                <input type="color" name="theme_primary_color" class="bx-input" style="height:44px;padding:4px;" value="<?= e(sanitize_hex_color((string) ($cfg['theme_primary_color'] ?? '#2B2F77'), '#2B2F77')) ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'অ্যাকসেন্ট রঙ' : 'Accent Color' ?></label>
                <input type="color" name="theme_accent_color" class="bx-input" style="height:44px;padding:4px;" value="<?= e(sanitize_hex_color((string) ($cfg['theme_accent_color'] ?? '#F4A62A'), '#F4A62A')) ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ফন্ট পেয়ার' : 'Font Pairing (Local / Offline)' ?></label>
                <?php $fp = (string) ($cfg['theme_font_pair'] ?? 'hind_noto'); ?>
                <select name="theme_font_pair" class="bx-select">
                    <option value="hind_noto" <?= $fp === 'hind_noto' ? 'selected' : '' ?>>Bookexa Classic (Hind Siliguri + Noto Serif)</option>
                    <option value="bengali_modern" <?= $fp === 'bengali_modern' ? 'selected' : '' ?>>Modern Sans (Hind Siliguri Uniform)</option>
                    <option value="editorial_serif" <?= $fp === 'editorial_serif' ? 'selected' : '' ?>>Editorial Serif Dominant</option>
                    <option value="system_clean" <?= $fp === 'system_clean' ? 'selected' : '' ?>>Clean System UI (Zero Webfonts)</option>
                </select>
            </div>
        </div>

        <div class="bx-grid-2" style="gap:12px;margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ফুটার টেক্সট (বাংলা)' : 'Custom Footer Text (Bangla)' ?></label>
                <input type="text" name="footer_text_bn" class="bx-input" placeholder="<?= e(t('tagline')) ?>" value="<?= e((string) ($cfg['footer_text_bn'] ?? '')) ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ফুটার টেক্সট (English)' : 'Custom Footer Text (English)' ?></label>
                <input type="text" name="footer_text_en" class="bx-input" placeholder="Your Bookstore Tagline" value="<?= e((string) ($cfg['footer_text_en'] ?? '')) ?>">
            </div>
        </div>

        <div class="bx-grid-3" style="gap:10px;">
            <div><label style="display:block;font-weight:600;margin-bottom:4px;">Facebook URL</label><input type="url" name="social_facebook" class="bx-input" placeholder="https://facebook.com/..." value="<?= e((string) ($cfg['social_facebook'] ?? '')) ?>"></div>
            <div><label style="display:block;font-weight:600;margin-bottom:4px;">YouTube URL</label><input type="url" name="social_youtube" class="bx-input" placeholder="https://youtube.com/..." value="<?= e((string) ($cfg['social_youtube'] ?? '')) ?>"></div>
            <div><label style="display:block;font-weight:600;margin-bottom:4px;">Telegram URL</label><input type="url" name="social_telegram" class="bx-input" placeholder="https://t.me/..." value="<?= e((string) ($cfg['social_telegram'] ?? '')) ?>"></div>
            <div><label style="display:block;font-weight:600;margin-bottom:4px;">WhatsApp URL</label><input type="url" name="social_whatsapp" class="bx-input" placeholder="https://wa.me/8801..." value="<?= e((string) ($cfg['social_whatsapp'] ?? '')) ?>"></div>
            <div><label style="display:block;font-weight:600;margin-bottom:4px;">Instagram URL</label><input type="url" name="social_instagram" class="bx-input" placeholder="https://instagram.com/..." value="<?= e((string) ($cfg['social_instagram'] ?? '')) ?>"></div>
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
        <div class="bx-grid-2" style="margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Copyright &amp; Watermark Notice (Bangla)</label>
                <textarea name="legal_copyright_bn" class="bx-input" rows="3"><?= e($cfg['legal_copyright_bn'] ?? '') ?></textarea>
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Copyright &amp; Watermark Notice (English)</label>
                <textarea name="legal_copyright_en" class="bx-input" rows="3"><?= e($cfg['legal_copyright_en'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="bx-grid-2" style="margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Editorial Policy (Bangla)</label>
                <textarea name="legal_editorial_bn" class="bx-input" rows="4"><?= e($cfg['legal_editorial_bn'] ?? '') ?></textarea>
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Editorial Policy (English)</label>
                <textarea name="legal_editorial_en" class="bx-input" rows="4"><?= e($cfg['legal_editorial_en'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="bx-grid-2" style="margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">How We Review Books (Bangla)</label>
                <textarea name="legal_review_policy_bn" class="bx-input" rows="4"><?= e($cfg['legal_review_policy_bn'] ?? '') ?></textarea>
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">How We Review Books (English)</label>
                <textarea name="legal_review_policy_en" class="bx-input" rows="4"><?= e($cfg['legal_review_policy_en'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="bx-grid-2">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Corrections Policy (Bangla)</label>
                <textarea name="legal_corrections_bn" class="bx-input" rows="3"><?= e($cfg['legal_corrections_bn'] ?? '') ?></textarea>
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Corrections Policy (English)</label>
                <textarea name="legal_corrections_en" class="bx-input" rows="3"><?= e($cfg['legal_corrections_en'] ?? '') ?></textarea>
            </div>
        </div>
    </section>

    <div class="bx-sticky-form-actions">
        <button type="submit" class="bx-btn bx-btn-primary" style="width:100%;">
            <?= $lang === 'bn' ? 'সকল সেটিংস সংরক্ষণ করুন' : 'Save All Settings' ?>
        </button>
    </div>
</form>

<section class="bx-card" style="margin-top:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;">
        <div style="flex:1 1 320px;min-width:0;">
            <h2 style="margin:0 0 4px;font-size:1.08rem;font-family:var(--font-serif);">
                <?= $lang === 'bn' ? 'CDN ক্রন সিঙ্ক কী ও URL' : 'CDN Cron Sync Key & URL' ?>
            </h2>
            <small style="color:var(--text-muted);display:block;margin-bottom:8px;">
                <?= $lang === 'bn'
                    ? 'সার্ভারের ক্রন জবে নিচের URL ব্যবহার করুন। নিরাপত্তা ঝুঁকি মনে হলে কী পরিবর্তন (Regenerate) করুন।'
                    : 'Use this URL in cPanel cron jobs to sync pending media automatically. Rotate the key anytime to revoke older cron links.' ?>
            </small>
            <input type="text" readonly class="bx-input" style="font-family:monospace;font-size:0.82rem;" value="<?= e(url('/cron/sync_media.php?key=' . cron_sync_token())) ?>">
        </div>
        <form method="post" action="<?= e(url('/admin/settings')) ?>" style="margin:0;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="regenerate_cron_key">
            <input type="hidden" name="_return" value="/admin/settings">
            <button type="submit" class="bx-btn bx-btn-outline" data-confirm="<?= e($lang === 'bn' ? 'নতুন ক্রন কী তৈরি করলে পুরনো ক্রন URL আর কাজ করবে না। নিশ্চিত?' : 'Regenerating the cron key will immediately invalidate the previous cron URL. Continue?') ?>">
                <?= $lang === 'bn' ? 'ক্রন কী রিজেনারেট করুন' : 'Regenerate cron key' ?>
            </button>
        </form>
    </div>
</section>

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
