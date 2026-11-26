<?php
declare(strict_types=1);

require_once APP_ROOT . '/includes/qrcode.php';

$lang = current_lang();
$forced = !empty($admin['must_change_password']);
$aid = (int) $admin['id'];
$totpRow = db_one('SELECT totp_enabled, backup_codes_json FROM admins WHERE id = ?', [$aid]);
$totpEnabled = !empty($totpRow['totp_enabled']);
$backupCount = 0;
if (!empty($totpRow['backup_codes_json'])) {
    $dec = json_decode((string) $totpRow['backup_codes_json'], true);
    $backupCount = is_array($dec) ? count($dec) : 0;
}
$pendingSecret = (string) ($_SESSION['admin_totp_setup_secret'] ?? '');
$freshBackupCodes = $_SESSION['admin_fresh_backup_codes'] ?? null;
unset($_SESSION['admin_fresh_backup_codes']);
$sessions = list_admin_sessions($aid);
$curSessTok = (string) ($_SESSION['admin_session_token'] ?? '');
$ipAllowlist = site_setting('admin_ip_allowlist', '');
?>

<?php if ($forced): ?>
    <div class="bx-alert bx-alert-warning" role="alert" style="margin-bottom:18px;">
        <?= $lang === 'bn'
            ? 'নিরাপত্তার জন্য প্রথমবার লগইনের পর আপনাকে অবশ্যই ডিফল্ট বা অস্থায়ী পাসওয়ার্ড পরিবর্তন করতে হবে।'
            : 'Security required: Please replace your initial or default password before using the Admin Panel.' ?>
    </div>
<?php endif; ?>

<div class="bx-grid-2" style="align-items:start;margin-bottom:20px;">
    <!-- 1. Password Change -->
    <section class="bx-card">
        <h2 style="margin:0 0 8px;font-size:1.15rem;font-family:var(--font-serif);">
            <?= $lang === 'bn' ? 'অ্যাডমিন পাসওয়ার্ড পরিবর্তন' : 'Change Admin Password' ?>
        </h2>
        <p style="margin:0 0 16px;color:var(--text-muted);font-size:0.88rem;">
            <?= $lang === 'bn' ? 'কমপক্ষে ৮ অক্ষরের শক্তিশালী নতুন পাসওয়ার্ড দিন।' : 'Choose a strong password of at least 8 characters.' ?>
        </p>

        <form method="post" action="<?= e(url('/admin/account-security')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_my_password">
            <div class="bx-field">
                <label for="cur-pw"><?= $lang === 'bn' ? 'বর্তমান পাসওয়ার্ড *' : 'Current Password *' ?></label>
                <input type="password" id="cur-pw" name="current_password" class="bx-input" required autocomplete="current-password">
            </div>
            <div class="bx-field">
                <label for="new-pw"><?= $lang === 'bn' ? 'নতুন পাসওয়ার্ড (৮+ অক্ষর) *' : 'New Password (8+ chars) *' ?></label>
                <input type="password" id="new-pw" name="new_password" class="bx-input" required minlength="8" autocomplete="new-password">
            </div>
            <div class="bx-field">
                <label for="cnf-pw"><?= $lang === 'bn' ? 'নতুন পাসওয়ার্ড নিশ্চিত করুন *' : 'Confirm New Password *' ?></label>
                <input type="password" id="cnf-pw" name="confirm_password" class="bx-input" required minlength="8" autocomplete="new-password">
            </div>
            <button type="submit" class="bx-btn bx-btn-primary" style="width:100%;">
                <?= $lang === 'bn' ? 'পাসওয়ার্ড আপডেট করুন' : 'Update Password & Continue' ?>
            </button>
        </form>
    </section>

    <!-- 2. TOTP Two-Factor Authentication (Google Authenticator + 8 Backup Codes) -->
    <section class="bx-card" id="admin-2fa">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
            <h2 style="margin:0;font-size:1.15rem;font-family:var(--font-serif);">
                <?= $lang === 'bn' ? 'টু-ফ্যাক্টর অথেনটিকেশন (TOTP 2FA)' : 'Two-Factor Authentication (TOTP)' ?>
            </h2>
            <span class="bx-status-pill <?= $totpEnabled ? 'st-active' : 'st-inactive' ?>">
                <?= $totpEnabled ? ($lang === 'bn' ? 'সক্রিয়' : 'Enabled') : ($lang === 'bn' ? 'নিষ্ক্রিয়' : 'Disabled') ?>
            </span>
        </div>
        <p style="margin:0 0 14px;color:var(--text-muted);font-size:0.88rem;">
            <?= $lang === 'bn'
                ? 'Google Authenticator বা যেকোনো TOTP অ্যাপ ও ৮টি জরুরি ব্যাকআপ কোড ব্যবহার করুন।'
                : 'Compatible with Google Authenticator / Authy plus 8 one-time recovery backup codes.' ?>
        </p>

        <?php if (is_array($freshBackupCodes) && !empty($freshBackupCodes)): ?>
            <div style="padding:14px;border-radius:12px;border:1.5px solid var(--gold);background:var(--bg-alt);margin-bottom:14px;">
                <strong style="display:block;margin-bottom:6px;"><?= $lang === 'bn' ? 'আপনার ৮টি ব্যাকআপ কোড (নিরাপদে সংরক্ষণ করুন):' : 'Save Your 8 One-Time Backup Codes:' ?></strong>
                <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;font-family:monospace;font-size:0.92rem;">
                    <?php foreach ($freshBackupCodes as $bc): ?>
                        <code style="padding:4px 8px;background:var(--surface);border-radius:6px;border:1px solid var(--border);"><?= e($bc) ?></code>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$totpEnabled && $pendingSecret === ''): ?>
            <form method="post" action="<?= e(url('/admin/account-security#admin-2fa')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="start_totp_setup">
                <button type="submit" class="bx-btn bx-btn-primary">
                    <?= $lang === 'bn' ? '2FA সেটআপ শুরু করুন' : 'Set Up Google Authenticator (2FA)' ?>
                </button>
            </form>
        <?php elseif (!$totpEnabled && $pendingSecret !== ''): ?>
            <?php
            $uri = totp_provisioning_uri(site_setting('site_name', 'Bookexa') . ' Admin', (string) $admin['email'], $pendingSecret);
            $qrSvg = render_qr_svg($uri, 168);
            ?>
            <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin-bottom:14px;padding:12px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);">
                <div style="background:#fff;padding:8px;border-radius:10px;line-height:0;"><?= $qrSvg ?></div>
                <div style="flex:1;min-width:180px;">
                    <small style="display:block;color:var(--text-muted);"><?= $lang === 'bn' ? 'অ্যাপ দিয়ে QR স্ক্যান করুন অথবা কী লিখুন:' : 'Scan QR code or enter manual key:' ?></small>
                    <code style="display:block;margin-top:4px;font-size:0.86rem;word-break:break-all;"><?= e($pendingSecret) ?></code>
                </div>
            </div>
            <form method="post" action="<?= e(url('/admin/account-security#admin-2fa')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="confirm_totp_setup">
                <div style="flex:1 1 180px;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'অ্যাপ থেকে ৬ অঙ্কের কোড *' : '6-Digit Verification Code *' ?></label>
                    <input type="text" name="totp_code" class="bx-input" required maxlength="6" inputmode="numeric" placeholder="123456">
                </div>
                <button type="submit" class="bx-btn bx-btn-primary"><?= $lang === 'bn' ? 'নিশ্চিত ও চালু করুন' : 'Verify & Enable 2FA' ?></button>
            </form>
        <?php else: ?>
            <p style="font-size:0.88rem;margin:0 0 12px;">
                <?= $lang === 'bn' ? "অবশিষ্ট ব্যাকআপ কোড: {$backupCount}টি" : "Remaining unused backup codes: <strong>{$backupCount}</strong>" ?>
            </p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <form method="post" action="<?= e(url('/admin/account-security#admin-2fa')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="regen_backup_codes">
                    <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'নতুন ৮টি ব্যাকআপ কোড তৈরি' : 'Regenerate 8 Backup Codes' ?></button>
                </form>
                <form method="post" action="<?= e(url('/admin/account-security#admin-2fa')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;" data-confirm-msg="<?= e($lang === 'bn' ? '2FA নিষ্ক্রিয় করবেন?' : 'Disable Two-Factor Authentication?') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="disable_totp">
                    <input type="password" name="current_password" class="bx-input" style="max-width:180px;" required placeholder="<?= e($lang === 'bn' ? 'পাসওয়ার্ড দিন' : 'Current password') ?>">
                    <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm" style="color:var(--rose-text);"><?= $lang === 'bn' ? '2FA বন্ধ করুন' : 'Disable 2FA' ?></button>
                </form>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- 3. Active Admin Sessions & Sign Out All -->
<section class="bx-card" id="admin-sessions" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <div>
            <h2 style="margin:0;font-size:1.12rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'সক্রিয় অ্যাডমিন সেশনসমূহ' : 'Active Admin Sessions' ?></h2>
            <small style="color:var(--text-muted);"><?= $lang === 'bn' ? 'আপনার বর্তমান IP: ' : 'Your current IP: ' ?><code><?= e(client_ip()) ?></code></small>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <form method="post" action="<?= e(url('/admin/account-security#admin-sessions')) ?>" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="signout_other_sessions">
                <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'অন্য সব সেশন লগআউট' : 'Sign Out Other Sessions' ?></button>
            </form>
            <form method="post" action="<?= e(url('/admin/account-security#admin-sessions')) ?>" style="margin:0;" data-confirm-msg="<?= e($lang === 'bn' ? 'বর্তমান সেশনসহ সব ডিভাইস থেকে লগআউট করবেন?' : 'Sign out ALL sessions including this browser?') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="signout_all_sessions">
                <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm" style="color:var(--rose-text);"><?= $lang === 'bn' ? 'সব সেশন সাইন আউট' : 'Sign Out All' ?></button>
            </form>
        </div>
    </div>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th>IP</th>
                    <th><?= $lang === 'bn' ? 'ব্রাউজার / ডিভাইস' : 'User Agent' ?></th>
                    <th><?= $lang === 'bn' ? 'সর্বশেষ সক্রিয়' : 'Last Active' ?></th>
                    <th><?= $lang === 'bn' ? 'অবস্থা' : 'Session' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$sessions): ?>
                    <tr class="bx-empty-row"><td colspan="4" style="color:var(--text-muted);"><?= $lang === 'bn' ? 'কোনো সক্রিয় সেশন নেই।' : 'No active sessions recorded.' ?></td></tr>
                <?php else: foreach ($sessions as $s): ?>
                    <?php $isThis = $curSessTok !== '' && hash_equals($curSessTok, (string) $s['session_token']); ?>
                    <tr>
                        <td class="bx-td-primary"><code><?= e((string) $s['ip_address']) ?></code></td>
                        <td data-label="Browser" style="font-size:0.82rem;color:var(--text-muted);max-width:340px;overflow-wrap:anywhere;"><?= e((string) ($s['user_agent'] ?: 'Browser Session')) ?></td>
                        <td data-label="Last Active" style="font-size:0.84rem;"><?= e(date('d M Y, H:i', strtotime((string) $s['last_active_at']))) ?></td>
                        <td data-label="Status">
                            <?= $isThis ? '<span class="bx-badge bx-badge-teal">Current Session</span>' : '<span class="bx-badge">Active</span>' ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- 4. Optional Admin IP Allowlist (Super Admin Only) -->
<?php if (($admin['role'] ?? '') === 'superadmin'): ?>
<section class="bx-card" id="admin-ip-allowlist">
    <h2 style="margin:0 0 8px;font-size:1.12rem;font-family:var(--font-serif);">
        <?= $lang === 'bn' ? 'ঐচ্ছিক অ্যাডমিন IP Allowlist' : 'Optional Admin IP Allowlist' ?>
    </h2>
    <p style="margin:0 0 12px;color:var(--text-muted);font-size:0.88rem;">
        <?= $lang === 'bn'
            ? 'খালি রাখলে সব IP অনুমোদিত। সীমাবদ্ধ করতে চাইলে প্রতি লাইনে বা কমা দিয়ে IP বা CIDR (যেমন 127.0.0.1, 103.10.20.0/24) দিন।'
            : 'Leave blank to allow any IP. Otherwise list allowed IPs or IPv4 CIDRs (comma/newline separated). Your current IP is' ?>
        <code><?= e(client_ip()) ?></code>.
    </p>
    <form method="post" action="<?= e(url('/admin/account-security#admin-ip-allowlist')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_ip_allowlist">
        <div class="bx-field">
            <textarea name="admin_ip_allowlist" class="bx-input" rows="3" placeholder="127.0.0.1&#10;::1&#10;192.168.1.0/24"><?= e($ipAllowlist) ?></textarea>
        </div>
        <button type="submit" class="bx-btn bx-btn-outline"><?= $lang === 'bn' ? 'IP Allowlist সংরক্ষণ করুন' : 'Save IP Allowlist' ?></button>
    </form>
</section>
<?php endif; ?>
