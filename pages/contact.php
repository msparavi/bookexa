<?php
declare(strict_types=1);
$lang = current_lang();
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $msg = trim((string) ($_POST['message'] ?? ''));
    if ($name !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && $msg !== '') {
        $sent = true;
    } else {
        flash('danger', $lang === 'bn' ? 'অনুগ্রহ করে নাম, সঠিক ইমেইল ও বার্তা দিন।' : 'Please enter your name, a valid email, and your message.');
    }
}
?>
<div class="container" style="padding-top:24px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(t('nav_contact')) ?></span>
    </nav>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:24px;margin-top:20px;">
        <div class="bx-card">
            <h1 class="bx-h2" style="margin-bottom:8px;"><?= e(t('contact_title')) ?></h1>
            <p class="bx-muted" style="margin:0 0 20px;"><?= e(t('contact_sub')) ?></p>

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
            </div>
        </div>

        <div class="bx-card">
            <h2 class="bx-h2" style="margin-bottom:16px;"><?= e(t('send_message')) ?></h2>
            <?php if ($sent): ?>
                <div style="padding:18px;border-radius:12px;background:rgba(22,121,79,0.12);border-left:4px solid var(--success);">
                    <?= $lang === 'bn' ? 'ধন্যবাদ! আপনার বার্তাটি গৃহীত হয়েছে। আমরা শীঘ্রই যোগাযোগ করবো।' : 'Thank you! Your message has been received. We will reply shortly.' ?>
                </div>
            <?php else: ?>
                <form method="post" action="<?= e(url('/contact')) ?>">
                    <?= csrf_field() ?>
                    <div class="bx-field">
                        <label for="c-name"><?= $lang === 'bn' ? 'আপনার নাম' : 'Your Name' ?></label>
                        <input class="bx-input" id="c-name" name="name" required value="<?= e($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="bx-field">
                        <label for="c-email"><?= $lang === 'bn' ? 'ইমেইল' : 'Email Address' ?></label>
                        <input class="bx-input" id="c-email" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="bx-field">
                        <label for="c-msg"><?= $lang === 'bn' ? 'বার্তা' : 'Message' ?></label>
                        <textarea class="bx-textarea" id="c-msg" name="message" rows="4" required><?= e($_POST['message'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="bx-btn bx-btn-primary"><?= icon('check') ?> <?= e(t('send_message')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
