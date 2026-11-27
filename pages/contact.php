<?php
declare(strict_types=1);
$lang = current_lang();
$sent = false;
$formError = null;
$nameVal = '';
$emailVal = '';
$phoneVal = '';
$subjectVal = '';
$msgVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $honeypot   = trim((string) ($_POST['website'] ?? ''));
    $nameVal    = trim((string) ($_POST['name'] ?? ''));
    $emailVal   = trim((string) ($_POST['email'] ?? ''));
    $phoneVal   = trim((string) ($_POST['phone'] ?? ''));
    $subjectVal = trim((string) ($_POST['subject'] ?? ''));
    $msgVal     = trim((string) ($_POST['message'] ?? ''));

    if ($honeypot !== '') {
        $formError = $lang === 'bn' ? 'অনুরোধ যাচাই করা যায়নি।' : 'Submission rejected.';
    } elseif ($nameVal === '' || !filter_var($emailVal, FILTER_VALIDATE_EMAIL) || $msgVal === '') {
        $formError = $lang === 'bn' ? 'অনুগ্রহ করে নাম, সঠিক ইমেইল ও বার্তা দিন।' : 'Please enter your name, a valid email, and your message.';
    } elseif (!user_rate_limit_check('contact:ip:' . client_ip(), 3, 3600)) {
        $formError = $lang === 'bn'
            ? 'প্রতি ঘণ্টায় সর্বোচ্চ ৩টি বার্তা পাঠানো যায়। অনুগ্রহ করে কিছুক্ষণ পর আবার চেষ্টা করুন।'
            : 'Too many messages sent from your IP (limit 3 per hour). Please try again later.';
    } else {
        db_exec(
            'INSERT INTO contact_messages (name, email, phone, subject, message, ip, status) VALUES (?, ?, ?, ?, ?, ?, "new")',
            [
                mb_substr($nameVal, 0, 120),
                mb_substr($emailVal, 0, 190),
                $phoneVal !== '' ? mb_substr($phoneVal, 0, 30) : null,
                $subjectVal !== '' ? mb_substr($subjectVal, 0, 190) : null,
                $msgVal,
                client_ip(),
            ]
        );
        $sent = true;

        // Email copy to admin via built-in SMTP mailer (never blocks form save if SMTP unconfigured)
        $adminTo = trim((string) (site_setting('contact_email', '') ?: site_setting('smtp_from_email', '')));
        if ($adminTo !== '' && filter_var($adminTo, FILTER_VALIDATE_EMAIL)) {
            require_once APP_ROOT . '/includes/mailer.php';
            try {
                send_bookexa_template_mail(
                    $adminTo,
                    'New Contact Message: ' . ($subjectVal !== '' ? $subjectVal : $nameVal),
                    'New Contact Form Inquiry',
                    '<p><strong>From:</strong> ' . e($nameVal) . ' (' . e($emailVal) . ')' . ($phoneVal !== '' ? ' · ' . e($phoneVal) : '') . '</p>'
                    . ($subjectVal !== '' ? '<p><strong>Subject:</strong> ' . e($subjectVal) . '</p>' : '')
                    . '<p><strong>Message:</strong><br>' . nl2br(e($msgVal)) . '</p>'
                    . '<p style="font-size:12px;color:#666;">IP: ' . e(client_ip()) . '</p>',
                    BASE_URL . '/admin/logs?filter=contact',
                    'Open Admin Logs'
                );
            } catch (Throwable $e) {
            }
        }

        $nameVal = $emailVal = $phoneVal = $subjectVal = $msgVal = '';
    }

    if ($formError !== null) {
        flash('danger', $formError);
    }
}
?>
<div class="container" style="padding-top:24px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(t('nav_contact')) ?></span>
    </nav>

    <div class="bx-page-hero is-contact" style="margin-top:16px;margin-bottom:22px;">
        <div class="bx-page-hero-copy">
            <span class="bx-badge bx-badge-accent" style="margin-bottom:8px;"><?= icon('phone') ?> <?= $lang === 'bn' ? 'পাঠক সহায়তা ডেস্ক' : 'Reader Helpdesk' ?></span>
            <h1 class="bx-display" style="font-size:clamp(1.5rem,3vw,2.1rem);margin:0 0 8px;"><?= e(t('contact_title')) ?></h1>
            <p style="margin:0;opacity:0.92;"><?= e(t('contact_sub')) ?></p>
        </div>
        <div class="bx-page-hero-art">
            <?= illustration_svg('contact') ?>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:24px;">
        <div class="bx-card">
            <h2 class="bx-h2" style="margin-bottom:14px;"><?= $lang === 'bn' ? 'যোগাযোগের মাধ্যম' : 'Direct Support Channels' ?></h2>

            <div style="display:flex;flex-direction:column;gap:14px;">
                <div style="padding:14px;border-radius:12px;background:var(--bg-alt);">
                    <strong><?= $lang === 'bn' ? 'ফোন' : 'Phone' ?>:</strong><br>
                    <?= e(site_setting('contact_phone', '+880 1700-000001')) ?>
                </div>
                <div style="padding:14px;border-radius:12px;background:var(--bg-alt);">
                    <strong><?= $lang === 'bn' ? 'ইমেইল' : 'Email' ?>:</strong><br>
                    <?= e(site_setting('contact_email', 'support@bookexa.com')) ?>
                </div>
                <div style="padding:14px;border-radius:12px;background:var(--bg-alt);">
                    <strong><?= $lang === 'bn' ? 'ঠিকানা' : 'Address' ?>:</strong><br>
                    <?= e(site_setting('contact_address', 'Dhaka, Bangladesh')) ?>
                </div>
                <div style="padding:14px;border-radius:12px;background:var(--bg-alt);">
                    <strong>bKash / Nagad:</strong><br>
                    <?= e(site_setting('bkash_number')) ?> <br>
                    <?= e(site_setting('nagad_number')) ?>
                </div>
                <div style="padding:14px;border-radius:12px;background:var(--bg-alt);">
                    <strong><?= $lang === 'bn' ? 'সম্পাদকীয় ও সংশোধনী যোগাযোগ' : 'Editorial &amp; Corrections' ?>:</strong><br>
                    <div class="bx-contact-policy-links">
                        <a href="<?= e(url('/editorial-policy')) ?>"><?= e(t('legal_editorial')) ?></a>
                        <a href="<?= e(url('/how-we-review')) ?>"><?= e(t('legal_review_policy')) ?></a>
                        <a href="<?= e(url('/corrections-policy')) ?>"><?= e(t('legal_corrections')) ?></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="bx-card">
            <h2 class="bx-h2" style="margin-bottom:16px;"><?= e(t('send_message')) ?></h2>
            <?php if ($sent): ?>
                <div id="contact-sent-banner" style="padding:18px;border-radius:12px;background:rgba(20,184,166,0.14);border-left:4px solid var(--success);">
                    <?= $lang === 'bn' ? 'ধন্যবাদ! আপনার বার্তাটি সংরক্ষিত হয়েছে। আমরা শীঘ্রই যোগাযোগ করবো।' : 'Thank you! Your message has been saved and forwarded to our team. We will reply shortly.' ?>
                </div>
            <?php else: ?>
                <form method="post" action="<?= e(url('/contact')) ?>">
                    <?= csrf_field() ?>
                    <div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;visibility:hidden;" aria-hidden="true">
                        <label for="c-website">Website</label>
                        <input type="text" id="c-website" name="website" tabindex="-1" autocomplete="off" value="">
                    </div>
                    <div class="bx-field">
                        <label for="c-name"><?= $lang === 'bn' ? 'আপনার নাম *' : 'Your Name *' ?></label>
                        <input class="bx-input" id="c-name" name="name" required value="<?= e($nameVal) ?>">
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,180px),1fr));gap:12px;">
                        <div class="bx-field">
                            <label for="c-email"><?= $lang === 'bn' ? 'ইমেইল *' : 'Email Address *' ?></label>
                            <input class="bx-input" id="c-email" type="email" name="email" required value="<?= e($emailVal) ?>">
                        </div>
                        <div class="bx-field">
                            <label for="c-phone"><?= $lang === 'bn' ? 'ফোন (ঐচ্ছিক)' : 'Phone (Optional)' ?></label>
                            <input class="bx-input" id="c-phone" type="tel" name="phone" value="<?= e($phoneVal) ?>">
                        </div>
                    </div>
                    <div class="bx-field">
                        <label for="c-subject"><?= $lang === 'bn' ? 'বিষয় (ঐচ্ছিক)' : 'Subject (Optional)' ?></label>
                        <input class="bx-input" id="c-subject" name="subject" value="<?= e($subjectVal) ?>">
                    </div>
                    <div class="bx-field">
                        <label for="c-msg"><?= $lang === 'bn' ? 'বার্তা *' : 'Message *' ?></label>
                        <textarea class="bx-textarea" id="c-msg" name="message" rows="4" required><?= e($msgVal) ?></textarea>
                    </div>
                    <button type="submit" class="bx-btn bx-btn-primary"><?= icon('check') ?> <?= e(t('send_message')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
