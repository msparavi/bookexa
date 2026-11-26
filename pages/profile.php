<?php
declare(strict_types=1);
/** @var array $user, ?string $error, array $devices, string $currentDevToken, ?array $pendingContact */
$lang = current_lang();
$pendingValid = is_array($pendingContact) && (int) ($pendingContact['exp'] ?? 0) >= time();
?>
<div class="container" style="padding-top:24px;max-width:780px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= $lang === 'bn' ? 'প্রোফাইল' : 'Profile' ?></span>
    </nav>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin:18px 0;">
        <a href="<?= e(url('/library')) ?>" class="bx-btn bx-btn-primary bx-btn-sm"><?= icon('book') ?> <?= e(t('nav_library')) ?></a>
        <a href="<?= e(url('/orders')) ?>" class="bx-btn bx-btn-outline bx-btn-sm"><?= icon('cart') ?> <?= $lang === 'bn' ? 'আমার অর্ডার' : 'My Orders' ?></a>
        <form method="post" action="<?= e(url('/logout')) ?>" style="margin:0;">
            <?= csrf_field() ?>
            <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= icon('logout') ?> <?= e(t('nav_logout')) ?></button>
        </form>
    </div>

    <?php if (!empty($error)): ?><div style="margin-bottom:16px;padding:12px 16px;border-radius:12px;border-left:4px solid var(--danger);background:var(--bg-alt);font-weight:600;"><?= e($error) ?></div><?php endif; ?>

    <!-- 1. Profile Name -->
    <div class="bx-card" style="margin-bottom:20px;">
        <h1 class="bx-h2" style="margin-bottom:14px;"><?= $lang === 'bn' ? 'প্রোফাইল তথ্য' : 'Profile Details' ?></h1>
        <form method="post" action="<?= e(url('/profile')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">
            <div class="bx-field">
                <label for="prof-name"><?= $lang === 'bn' ? 'পূর্ণ নাম' : 'Full Name' ?></label>
                <input class="bx-input" id="prof-name" name="name" required value="<?= e($user['name']) ?>">
            </div>
            <div class="bx-grid-2" style="margin-bottom:12px;">
                <div class="bx-field" style="margin:0;">
                    <label><?= $lang === 'bn' ? 'বর্তমান ইমেইল' : 'Current Email' ?></label>
                    <input class="bx-input" value="<?= e($user['email']) ?>" disabled>
                </div>
                <div class="bx-field" style="margin:0;">
                    <label><?= $lang === 'bn' ? 'বর্তমান মোবাইল নম্বর' : 'Current Phone' ?></label>
                    <input class="bx-input" value="<?= e($user['phone'] ?? ($lang === 'bn' ? 'যুক্ত নয়' : 'Not set')) ?>" disabled>
                </div>
            </div>
            <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= icon('check') ?> <?= $lang === 'bn' ? 'নাম সংরক্ষণ করুন' : 'Save Name' ?></button>
        </form>
    </div>

    <!-- 2. Change Email or Phone with Verification -->
    <div class="bx-card" id="contact-verify" style="margin-bottom:20px;">
        <h2 class="bx-h2" style="font-size:1.2rem;margin-bottom:6px;"><?= $lang === 'bn' ? 'ইমেইল বা মোবাইল নম্বর পরিবর্তন (ভেরিফিকেশনসহ)' : 'Change Email or Phone (Verified)' ?></h2>
        <p class="bx-muted" style="margin:0 0 14px;font-size:0.88rem;"><?= $lang === 'bn' ? 'নতুন ঠিকানায় ৬ অঙ্কের কোড পাঠিয়ে যাচাই করা হবে।' : 'We send a 6-digit verification code to confirm your new contact.' ?></p>

        <?php if ($pendingValid): ?>
            <div style="padding:14px;border-radius:12px;border:1px solid var(--primary);background:var(--bg-alt);margin-bottom:16px;">
                <p style="margin:0 0 10px;font-weight:600;font-size:0.92rem;">
                    <?= $lang === 'bn' ? 'যাচাই কোড লিখুন:' : 'Verify:' ?> <code><?= e((string) $pendingContact['target']) ?></code>
                </p>
                <form method="post" action="<?= e(url('/profile')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="confirm_contact_verify">
                    <input class="bx-input" style="max-width:190px;" name="otp_code" required maxlength="6" inputmode="numeric" placeholder="6-digit OTP">
                    <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= icon('check') ?> <?= $lang === 'bn' ? 'যাচাই ও আপডেট' : 'Verify & Update' ?></button>
                </form>
            </div>
        <?php endif; ?>

        <div class="bx-grid-2">
            <form method="post" action="<?= e(url('/profile')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="request_contact_verify">
                <input type="hidden" name="contact_type" value="email">
                <div class="bx-field">
                    <label for="new-email"><?= $lang === 'bn' ? 'নতুন ইমেইল' : 'New Email Address' ?></label>
                    <input class="bx-input" id="new-email" type="email" name="new_value" required placeholder="new@example.com">
                </div>
                <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'ইমেইলে কোড পাঠান' : 'Send Email Code' ?></button>
            </form>
            <form method="post" action="<?= e(url('/profile')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="request_contact_verify">
                <input type="hidden" name="contact_type" value="phone">
                <div class="bx-field">
                    <label for="new-phone"><?= $lang === 'bn' ? 'নতুন মোবাইল নম্বর' : 'New Phone Number' ?></label>
                    <input class="bx-input" id="new-phone" name="new_value" required placeholder="01XXXXXXXXX">
                </div>
                <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'নম্বরে কোড পাঠান' : 'Send Phone Code' ?></button>
            </form>
        </div>
    </div>

    <!-- 3. Active Devices -->
    <div class="bx-card" id="my-devices" style="margin-bottom:20px;">
        <h2 class="bx-h2" style="font-size:1.2rem;margin-bottom:6px;"><?= $lang === 'bn' ? 'আমার সক্রিয় ডিভাইসসমূহ' : 'My Logged-In Devices' ?></h2>
        <p class="bx-muted" style="margin:0 0 14px;font-size:0.88rem;"><?= $lang === 'bn' ? 'একসাথে একাধিক ডিভাইস লগইন থাকলেও একটিতে রিডার চালু থাকবে।' : 'Manage active browsers signed into your account.' ?></p>
        <?php if (empty($devices)): ?>
            <p class="bx-muted" style="margin:0;"><?= $lang === 'bn' ? 'কোনো সক্রিয় ডিভাইস পাওয়া যায়নি।' : 'No active devices recorded yet.' ?></p>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php foreach ($devices as $dev): ?>
                    <?php $isCurrent = $currentDevToken !== '' && hash_equals($currentDevToken, (string) $dev['device_token']); ?>
                    <div class="bx-device-card">
                        <div>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <strong><?= e((string) $dev['device_label']) ?></strong>
                                <?php if ($isCurrent): ?>
                                    <span class="bx-badge bx-badge-teal"><?= $lang === 'bn' ? 'এই ডিভাইস' : 'This device' ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="bx-muted" style="font-size:0.82rem;margin-top:2px;">
                                IP: <?= e((string) $dev['ip_address']) ?> ·
                                <?= $lang === 'bn' ? 'সর্বশেষ সক্রিয়:' : 'Last active:' ?>
                                <?= e(date('d M Y, H:i', strtotime((string) $dev['last_active_at']))) ?>
                            </div>
                        </div>
                        <form method="post" action="<?= e(url('/profile')) ?>" style="margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="revoke_device">
                            <input type="hidden" name="device_id" value="<?= (int) $dev['id'] ?>">
                            <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm">
                                <?= $lang === 'bn' ? 'এই ডিভাইস থেকে সাইন আউট' : 'Sign out this device' ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 4. Password -->
    <div class="bx-card" style="margin-bottom:20px;">
        <h2 class="bx-h2" style="font-size:1.2rem;margin-bottom:14px;"><?= $lang === 'bn' ? 'পাসওয়ার্ড পরিবর্তন করুন' : 'Change Password' ?></h2>
        <form method="post" action="<?= e(url('/profile')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="bx-field">
                <label for="cur-pw"><?= $lang === 'bn' ? 'বর্তমান পাসওয়ার্ড' : 'Current Password' ?></label>
                <input class="bx-input" id="cur-pw" type="password" name="current_password" required>
            </div>
            <div class="bx-field">
                <label for="nxt-pw"><?= $lang === 'bn' ? 'নতুন পাসওয়ার্ড (কমপক্ষে ৬ অক্ষর)' : 'New Password (min 6 chars)' ?></label>
                <input class="bx-input" id="nxt-pw" type="password" name="new_password" required minlength="6">
            </div>
            <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= icon('lock') ?> <?= $lang === 'bn' ? 'পাসওয়ার্ড বদলান' : 'Update Password' ?></button>
        </form>
    </div>

    <!-- 5. Privacy: Download My Data & Delete Account -->
    <div class="bx-card">
        <h2 class="bx-h2" style="font-size:1.2rem;margin-bottom:10px;"><?= $lang === 'bn' ? 'ডেটা ডাউনলোড ও অ্যাকাউন্ট মুছুন' : 'Your Data & Account Deletion' ?></h2>
        <div style="margin-bottom:18px;">
            <form method="post" action="<?= e(url('/profile')) ?>" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="download_my_data">
                <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm">
                    <?= $lang === 'bn' ? 'আমার সব ডেটা ডাউনলোড করুন (JSON)' : 'Download My Data (JSON)' ?>
                </button>
            </form>
        </div>

        <details style="border-top:1px solid var(--border);padding-top:14px;">
            <summary style="cursor:pointer;font-weight:700;color:var(--rose-text);min-height:40px;display:flex;align-items:center;">
                <?= $lang === 'bn' ? 'স্থায়ীভাবে অ্যাকাউন্ট মুছে ফেলুন' : 'Permanently Delete My Account' ?>
            </summary>
            <form method="post" action="<?= e(url('/profile')) ?>" style="margin-top:12px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_account">
                <p class="bx-muted" style="font-size:0.86rem;margin:0 0 10px;">
                    <?= $lang === 'bn' ? 'সতর্কতা: এটি করলে আপনার লাইব্রেরি, বুকমার্ক ও অর্ডারের সব তথ্য স্থায়ীভাবে মুছে যাবে।' : 'Warning: This permanently deletes your library access, bookmarks, reviews, and profile data.' ?>
                </p>
                <div class="bx-grid-2">
                    <div class="bx-field">
                        <label for="del-pw"><?= $lang === 'bn' ? 'বর্তমান পাসওয়ার্ড' : 'Current Password' ?></label>
                        <input class="bx-input" id="del-pw" type="password" name="current_password" required>
                    </div>
                    <div class="bx-field">
                        <label for="del-word"><?= $lang === 'bn' ? 'নিশ্চিত করতে DELETE লিখুন' : 'Type DELETE to confirm' ?></label>
                        <input class="bx-input" id="del-word" name="confirm_delete" required placeholder="DELETE">
                    </div>
                </div>
                <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm" style="border-color:var(--rose-text);color:var(--rose-text);"
                        data-confirm="<?= e($lang === 'bn' ? 'আপনি কি নিশ্চিতভাবে অ্যাকাউন্ট স্থায়ীভাবে মুছতে চান?' : 'Are you absolutely sure you want to permanently delete your account?') ?>">
                    <?= $lang === 'bn' ? 'হ্যাঁ, অ্যাকাউন্ট মুছে ফেলুন' : 'Delete My Account Forever' ?>
                </button>
            </form>
        </details>
    </div>
</div>
