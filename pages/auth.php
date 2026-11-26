<?php
declare(strict_types=1);
/** @var string $authMode ('login'|'register'|'forgot'|'reset'|'device_limit'|'otp'), ?string $error, ?string $info, string $next, string $resetToken, array $devices, string $otpPhone, string $otpStep */
$lang = current_lang();
$otpEnabled = function_exists('sms_gateway_enabled') && sms_gateway_enabled();
$showLabel = $lang === 'bn' ? 'দেখুন' : 'Show password';
$hideLabel = $lang === 'bn' ? 'লুকান' : 'Hide password';
$errCls    = !empty($error) ? ' is-invalid' : '';
$errAttr   = !empty($error) ? ' aria-invalid="true"' : '';
$pwEyeSvg = '<svg class="bx-icon bx-pw-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><svg class="bx-icon bx-pw-eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
?>
<div class="container bx-auth-page">
    <div class="bx-auth-grid">
        <div class="bx-card bx-auth-card">
            <?php if ($authMode === 'login'): ?>
                <h1 class="bx-h2" style="margin-bottom:6px;"><?= $lang === 'bn' ? 'অ্যাকাউন্টে লগইন করুন' : 'Sign in to Bookexa' ?></h1>
                <p class="bx-muted" style="margin:0 0 18px;font-size:0.92rem;"><?= $lang === 'bn' ? 'আপনার লাইব্রেরি ও নিরাপদ পিডিএফ রিডার ব্যবহার করুন।' : 'Access your digital library and protected PDF reader.' ?></p>
                <?php if (!empty($error)): ?><div class="bx-auth-alert is-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
                <form method="post" action="<?= e(url('/login')) ?>" data-loading-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password_login">
                    <input type="hidden" name="next" value="<?= e($next ?? '') ?>">
                    <div class="bx-field">
                        <label for="u-id"><?= $lang === 'bn' ? 'ইমেইল বা মোবাইল নম্বর' : 'Email or Phone Number' ?></label>
                        <input class="bx-input<?= $errCls ?>"<?= $errAttr ?> id="u-id" name="identifier" required autocomplete="username" placeholder="<?= $lang === 'bn' ? 'you@example.com বা 01XXXXXXXXX' : 'you@example.com or 01XXXXXXXXX' ?>" value="<?= e($_POST['identifier'] ?? '') ?>">
                    </div>
                    <div class="bx-field">
                        <label for="u-pw"><?= $lang === 'bn' ? 'পাসওয়ার্ড' : 'Password' ?></label>
                        <div class="bx-pw-wrap">
                            <input class="bx-input<?= $errCls ?>"<?= $errAttr ?> id="u-pw" type="password" name="password" required autocomplete="current-password" placeholder="<?= $lang === 'bn' ? 'আপনার পাসওয়ার্ড লিখুন' : 'Enter your password' ?>">
                            <button type="button" class="bx-pw-toggle" data-pw-toggle="u-pw" data-label-show="<?= e($showLabel) ?>" data-label-hide="<?= e($hideLabel) ?>" aria-label="<?= e($showLabel) ?>" aria-pressed="false"><?= $pwEyeSvg ?></button>
                        </div>
                    </div>
                    <div style="text-align:right;margin:-6px 0 16px;font-size:0.88rem;">
                        <a href="<?= e(url('/forgot-password')) ?>" style="color:var(--primary);font-weight:600;display:inline-flex;align-items:center;min-height:40px;"><?= $lang === 'bn' ? 'পাসওয়ার্ড ভুলে গেছেন?' : 'Forgot password?' ?></a>
                    </div>
                    <button type="submit" class="bx-btn bx-btn-primary" style="width:100%;" data-loading-label="<?= e($lang === 'bn' ? 'লগইন হচ্ছে...' : 'Signing in...') ?>"><?= e(t('nav_login')) ?></button>
                </form>
                <?php if ($otpEnabled): ?>
                    <div style="margin-top:12px;">
                        <a href="<?= e(url('/login?mode=otp' . (!empty($next) ? '&next=' . rawurlencode($next) : ''))) ?>" class="bx-btn bx-btn-outline" style="width:100%;">
                            <?= icon('phone') ?> <?= $lang === 'bn' ? 'মোবাইল OTP দিয়ে লগইন করুন' : 'Sign in with Phone OTP' ?>
                        </a>
                    </div>
                <?php endif; ?>
                <p style="text-align:center;margin:18px 0 0;font-size:0.92rem;" class="bx-muted">
                    <?= $lang === 'bn' ? 'নতুন অ্যাকাউন্ট?' : 'New here?' ?>
                    <a href="<?= e(url('/register' . (!empty($next) ? '?next=' . rawurlencode($next) : ''))) ?>" style="color:var(--gold-text);font-weight:700;"><?= $lang === 'bn' ? 'রেজিস্টার করুন' : 'Create Account' ?></a>
                </p>

            <?php elseif ($authMode === 'otp'): ?>
                <h1 class="bx-h2" style="margin-bottom:6px;"><?= $lang === 'bn' ? 'মোবাইল OTP লগইন' : 'Sign in with Phone OTP' ?></h1>
                <p class="bx-muted" style="margin:0 0 18px;font-size:0.92rem;"><?= $lang === 'bn' ? 'নিবন্ধিত মোবাইল নম্বরে ৬ অঙ্কের কোড পাঠান।' : 'Receive a 6-digit verification code on your registered phone.' ?></p>
                <?php if (!empty($error)): ?><div class="bx-auth-alert is-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
                <?php if (!empty($info)): ?><div class="bx-auth-alert is-success" role="status"><?= $info ?></div><?php endif; ?>
                <?php if (($otpStep ?? 'phone') === 'code'): ?>
                    <form method="post" action="<?= e(url('/login')) ?>" data-loading-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="verify_login_otp">
                        <input type="hidden" name="next" value="<?= e($next ?? '') ?>">
                        <div class="bx-field">
                            <label for="o-phone"><?= $lang === 'bn' ? 'মোবাইল নম্বর' : 'Phone Number' ?></label>
                            <input class="bx-input" id="o-phone" name="phone" required value="<?= e($otpPhone ?? '') ?>" readonly>
                        </div>
                        <div class="bx-field">
                            <label for="o-code"><?= $lang === 'bn' ? '৬ অঙ্কের OTP কোড' : '6-Digit OTP Code' ?></label>
                            <input class="bx-input" id="o-code" name="otp_code" required inputmode="numeric" maxlength="6" placeholder="123456" autocomplete="one-time-code">
                        </div>
                        <button type="submit" class="bx-btn bx-btn-primary" style="width:100%;" data-loading-label="<?= e($lang === 'bn' ? 'যাচাই হচ্ছে...' : 'Verifying...') ?>"><?= $lang === 'bn' ? 'কোড যাচাই ও লগইন' : 'Verify & Sign In' ?></button>
                    </form>
                    <form method="post" action="<?= e(url('/login')) ?>" style="margin-top:10px;" data-otp-resend-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="send_login_otp">
                        <input type="hidden" name="next" value="<?= e($next ?? '') ?>">
                        <input type="hidden" name="phone" value="<?= e($otpPhone ?? '') ?>">
                        <?php $cdSec = max(0, (int) ($otpCooldown ?? 60)); ?>
                        <button type="submit"
                                class="bx-btn bx-btn-outline bx-btn-sm"
                                style="width:100%;"
                                data-otp-countdown="<?= $cdSec ?>"
                                data-wait-tpl="<?= e($lang === 'bn' ? 'পুনরায় পাঠান (%ss)' : 'Resend OTP in %ss') ?>"
                                data-ready-label="<?= e($lang === 'bn' ? 'পুনরায় OTP পাঠান' : 'Resend OTP Code') ?>"
                                <?= $cdSec > 0 ? 'disabled' : '' ?>>
                            <?= $cdSec > 0
                                ? e(($lang === 'bn' ? "পুনরায় পাঠান ({$cdSec}s)" : "Resend OTP in {$cdSec}s"))
                                : e($lang === 'bn' ? 'পুনরায় OTP পাঠান' : 'Resend OTP Code') ?>
                        </button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/login')) ?>" data-loading-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="send_login_otp">
                        <input type="hidden" name="next" value="<?= e($next ?? '') ?>">
                        <div class="bx-field">
                            <label for="o-phone"><?= $lang === 'bn' ? 'মোবাইল নম্বর' : 'Phone Number' ?></label>
                            <input class="bx-input" id="o-phone" name="phone" required autocomplete="tel" placeholder="01XXXXXXXXX" value="<?= e($otpPhone ?? '') ?>">
                        </div>
                        <button type="submit" class="bx-btn bx-btn-primary" style="width:100%;" data-loading-label="<?= e($lang === 'bn' ? 'পাঠানো হচ্ছে...' : 'Sending OTP...') ?>"><?= $lang === 'bn' ? 'OTP কোড পাঠান' : 'Send OTP Code' ?></button>
                    </form>
                <?php endif; ?>
                <p style="text-align:center;margin:16px 0 0;font-size:0.9rem;">
                    <a href="<?= e(url('/login')) ?>" style="color:var(--primary);font-weight:600;"><?= $lang === 'bn' ? 'পাসওয়ার্ড দিয়ে লগইন করুন' : 'Back to password login' ?></a>
                </p>

            <?php elseif ($authMode === 'device_limit'): ?>
                <h1 class="bx-h2" style="margin-bottom:6px;"><?= $lang === 'bn' ? 'ডিভাইস সীমা পূর্ণ হয়েছে' : 'Device Limit Reached' ?></h1>
                <?php if (!empty($error)): ?><div class="bx-auth-alert is-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
                <div style="display:flex;flex-direction:column;gap:10px;margin:16px 0;">
                    <?php foreach ($devices ?? [] as $dev): ?>
                        <form method="post" action="<?= e(url('/login')) ?>" class="bx-device-card">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="replace_device">
                            <input type="hidden" name="next" value="<?= e($next ?? '') ?>">
                            <input type="hidden" name="device_id" value="<?= (int) $dev['id'] ?>">
                            <div>
                                <strong><?= e((string) $dev['device_label']) ?></strong>
                                <div class="bx-muted" style="font-size:0.82rem;">IP: <?= e((string) $dev['ip_address']) ?> · <?= e(date('d M Y, H:i', strtotime((string) $dev['last_active_at']))) ?></div>
                            </div>
                            <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'সাইন আউট ও লগইন' : 'Sign out & use this browser' ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
                <a href="<?= e(url('/login')) ?>" class="bx-btn bx-btn-ghost bx-btn-sm"><?= $lang === 'bn' ? 'বাতিল করুন' : 'Cancel' ?></a>

            <?php elseif ($authMode === 'register'): ?>
                <h1 class="bx-h2" style="margin-bottom:6px;"><?= $lang === 'bn' ? 'নতুন অ্যাকাউন্ট খুলুন' : 'Create Bookexa Account' ?></h1>
                <p class="bx-muted" style="margin:0 0 18px;font-size:0.92rem;"><?= $lang === 'bn' ? 'ফ্রি স্যাম্পল ও কেনা বই পড়তে সাইন আপ করুন।' : 'Sign up to read free samples and manage your library.' ?></p>
                <?php if (!empty($error)): ?><div class="bx-auth-alert is-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
                <form method="post" action="<?= e(url('/register')) ?>" data-loading-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="next" value="<?= e($next ?? '') ?>">
                    <div class="bx-field">
                        <label for="r-name"><?= $lang === 'bn' ? 'পূর্ণ নাম *' : 'Full Name *' ?></label>
                        <input class="bx-input" id="r-name" name="name" required autocomplete="name" placeholder="<?= $lang === 'bn' ? 'আপনার পূর্ণ নাম' : 'Enter your full name' ?>" value="<?= e($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="bx-field">
                        <label for="r-email"><?= $lang === 'bn' ? 'ইমেইল ঠিকানা' : 'Email Address' ?></label>
                        <input class="bx-input" id="r-email" type="email" name="email" autocomplete="email" placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="bx-field">
                        <label for="r-phone"><?= $lang === 'bn' ? 'মোবাইল নম্বর' : 'Phone Number' ?></label>
                        <input class="bx-input" id="r-phone" name="phone" autocomplete="tel" placeholder="01XXXXXXXXX" value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="bx-field">
                        <label for="r-pw"><?= $lang === 'bn' ? 'পাসওয়ার্ড (কমপক্ষে ৬ অক্ষর) *' : 'Password (min 6 chars) *' ?></label>
                        <div class="bx-pw-wrap">
                            <input class="bx-input" id="r-pw" type="password" name="password" required minlength="6" autocomplete="new-password" placeholder="<?= $lang === 'bn' ? 'কমপক্ষে ৬ অক্ষরের পাসওয়ার্ড' : 'At least 6 characters' ?>">
                            <button type="button" class="bx-pw-toggle" data-pw-toggle="r-pw" data-label-show="<?= e($showLabel) ?>" data-label-hide="<?= e($hideLabel) ?>" aria-label="<?= e($showLabel) ?>" aria-pressed="false"><?= $pwEyeSvg ?></button>
                        </div>
                    </div>
                    <button type="submit" class="bx-btn bx-btn-primary" style="width:100%;" data-loading-label="<?= e($lang === 'bn' ? 'তৈরি হচ্ছে...' : 'Creating account...') ?>"><?= $lang === 'bn' ? 'অ্যাকাউন্ট তৈরি করুন' : 'Register' ?></button>
                </form>
                <p style="text-align:center;margin:18px 0 0;font-size:0.92rem;" class="bx-muted">
                    <?= $lang === 'bn' ? 'আগেই অ্যাকাউন্ট আছে?' : 'Already have an account?' ?>
                    <a href="<?= e(url('/login')) ?>" style="color:var(--gold-text);font-weight:700;"><?= e(t('nav_login')) ?></a>
                </p>

            <?php elseif ($authMode === 'forgot'): ?>
                <h1 class="bx-h2" style="margin-bottom:6px;"><?= $lang === 'bn' ? 'পাসওয়ার্ড রিসেট' : 'Reset Password' ?></h1>
                <p class="bx-muted" style="margin:0 0 18px;font-size:0.92rem;"><?= $lang === 'bn' ? 'আপনার নিবন্ধিত ইমেইল বা ফোন নম্বর দিন।' : 'Enter your registered email or phone number.' ?></p>
                <?php if (!empty($error)): ?><div class="bx-auth-alert is-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
                <?php if (!empty($info)): ?><div class="bx-auth-alert is-success" style="word-break:break-word;"><?= $info ?></div><?php endif; ?>
                <form method="post" action="<?= e(url('/forgot-password')) ?>" data-loading-form>
                    <?= csrf_field() ?>
                    <div class="bx-field">
                        <label for="f-id"><?= $lang === 'bn' ? 'ইমেইল বা মোবাইল নম্বর' : 'Email or Phone' ?></label>
                        <input class="bx-input" id="f-id" name="identifier" required placeholder="<?= $lang === 'bn' ? 'নিবন্ধিত ইমেইল বা ফোন নম্বর' : 'Registered email or phone' ?>">
                    </div>
                    <button type="submit" class="bx-btn bx-btn-primary" style="width:100%;" data-loading-label="<?= e($lang === 'bn' ? 'তৈরি হচ্ছে...' : 'Generating...') ?>"><?= $lang === 'bn' ? 'রিসেট লিংক তৈরি করুন' : 'Send Reset Link' ?></button>
                </form>

            <?php elseif ($authMode === 'reset'): ?>
                <h1 class="bx-h2" style="margin-bottom:16px;"><?= $lang === 'bn' ? 'নতুন পাসওয়ার্ড সেট করুন' : 'Set New Password' ?></h1>
                <?php if (!empty($error)): ?><div class="bx-auth-alert is-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
                <form method="post" action="<?= e(url('/reset-password')) ?>" data-loading-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($resetToken ?? '') ?>">
                    <div class="bx-field">
                        <label for="n-pw"><?= $lang === 'bn' ? 'নতুন পাসওয়ার্ড *' : 'New Password *' ?></label>
                        <div class="bx-pw-wrap">
                            <input class="bx-input" id="n-pw" type="password" name="password" required minlength="6" autocomplete="new-password" placeholder="<?= $lang === 'bn' ? 'কমপক্ষে ৬ অক্ষরের নতুন পাসওয়ার্ড' : 'Minimum 6 characters' ?>">
                            <button type="button" class="bx-pw-toggle" data-pw-toggle="n-pw" data-label-show="<?= e($showLabel) ?>" data-label-hide="<?= e($hideLabel) ?>" aria-label="<?= e($showLabel) ?>" aria-pressed="false"><?= $pwEyeSvg ?></button>
                        </div>
                    </div>
                    <button type="submit" class="bx-btn bx-btn-primary" style="width:100%;" data-loading-label="<?= e($lang === 'bn' ? 'আপডেট হচ্ছে...' : 'Updating...') ?>"><?= $lang === 'bn' ? 'পাসওয়ার্ড আপডেট করুন' : 'Update Password' ?></button>
                </form>
            <?php endif; ?>
        </div>

        <aside class="bx-auth-aside" aria-label="<?= $lang === 'bn' ? 'বুকএক্সা সুবিধা' : 'Bookexa Highlights' ?>">
            <div class="bx-auth-illus" aria-hidden="true">
                <svg viewBox="0 0 180 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="20" y="24" width="64" height="78" rx="10" fill="rgba(251,246,236,0.22)" stroke="#F4A62A" stroke-width="2.5"/>
                    <rect x="96" y="24" width="64" height="78" rx="10" fill="rgba(251,246,236,0.92)" stroke="#FBF6EC" stroke-width="2"/>
                    <path d="M90 28V100" stroke="#F4A62A" stroke-width="3" stroke-linecap="round"/>
                    <path d="M34 44H70M34 58H68M34 72H60" stroke="#FBF6EC" stroke-width="3" stroke-linecap="round"/>
                    <path d="M110 44H146M110 58H144M110 72H134" stroke="#2B2F77" stroke-width="3" stroke-linecap="round"/>
                    <circle cx="144" cy="22" r="14" fill="#14B8A6"/>
                    <path d="M138 22L142 26L150 18" stroke="#0B1020" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <span class="bx-badge bx-badge-gold" style="align-self:flex-start;"><?= e($lang === 'bn' ? 'ডিজিটাল বাংলা লাইব্রেরি' : 'Bangla Digital Library') ?></span>
            <h2 class="bx-display" style="margin:12px 0 8px;font-size:clamp(1.35rem,2.4vw,1.8rem);color:#FBF6EC;">
                <?= e($lang === 'bn' ? 'সব একাডেমিক ও সাহিত্য পিডিএফ এক জায়গায়' : 'Curated Academic & Literature PDFs in One Sanctuary') ?>
            </h2>
            <p style="margin:0 0 18px;opacity:0.9;line-height:1.65;">
                <?= e($lang === 'bn' ? 'ফ্রি স্যাম্পল পড়ে দেখুন, এক ক্লিকে লাইব্রেরি সংরক্ষণ করুন এবং মোবাইল ও ডেস্কটপে ঝকঝকে রিডার উপভোগ করুন।' : 'Preview free samples, save books to your personal library, and read crisply on mobile or desktop.') ?>
            </p>
            <ul class="bx-auth-perks">
                <li><?= icon('shield') ?> <span><?= e($lang === 'bn' ? 'নিরাপদ স্ট্রিমিং ও একক অ্যাকাউন্ট সুরক্ষা' : 'Encrypted streaming & single-session protection') ?></span></li>
                <li><?= icon('book') ?> <span><?= e($lang === 'bn' ? 'ডার্ক, লাইট ও সেপিয়া রিডার থিম' : 'Light, Dark & Sepia mobile-first PDF reader') ?></span></li>
                <li><?= icon('globe') ?> <span><?= e($lang === 'bn' ? 'বাংলা ও ইংরেজি দ্বিভাষিক ইন্টারফেস' : 'Bilingual Bangla & English experience') ?></span></li>
            </ul>
        </aside>
    </div>
</div>
