<?php
declare(strict_types=1);
/** @var array $user, ?string $error */
$lang = current_lang();
?>
<div class="container" style="padding-top:24px;max-width:760px;">
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

    <div class="bx-card" style="margin-bottom:22px;">
        <h1 class="bx-h2" style="margin-bottom:16px;"><?= $lang === 'bn' ? 'প্রোফাইল তথ্য' : 'Profile Settings' ?></h1>
        <?php if (!empty($error)): ?><div style="margin-bottom:14px;padding:10px 14px;border-radius:10px;border-left:4px solid var(--danger);background:var(--bg-alt);"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="<?= e(url('/profile')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">
            <div class="bx-field">
                <label><?= $lang === 'bn' ? 'নাম' : 'Full Name' ?></label>
                <input class="bx-input" name="name" required value="<?= e($user['name']) ?>">
            </div>
            <div class="bx-field">
                <label><?= $lang === 'bn' ? 'ইমেইল' : 'Email' ?></label>
                <input class="bx-input" value="<?= e($user['email']) ?>" disabled>
            </div>
            <div class="bx-field">
                <label><?= $lang === 'bn' ? 'মোবাইল নম্বর' : 'Phone Number' ?></label>
                <input class="bx-input" name="phone" value="<?= e($user['phone'] ?? '') ?>">
            </div>
            <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= icon('check') ?> <?= $lang === 'bn' ? 'সংরক্ষণ করুন' : 'Save Changes' ?></button>
        </form>
    </div>

    <div class="bx-card">
        <h2 class="bx-h2" style="font-size:1.2rem;margin-bottom:14px;"><?= $lang === 'bn' ? 'পাসওয়ার্ড পরিবর্তন করুন' : 'Change Password' ?></h2>
        <form method="post" action="<?= e(url('/profile')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="bx-field">
                <label><?= $lang === 'bn' ? 'বর্তমান পাসওয়ার্ড' : 'Current Password' ?></label>
                <input class="bx-input" type="password" name="current_password" required>
            </div>
            <div class="bx-field">
                <label><?= $lang === 'bn' ? 'নতুন পাসওয়ার্ড (কমপক্ষে ৬ অক্ষর)' : 'New Password (min 6 chars)' ?></label>
                <input class="bx-input" type="password" name="new_password" required minlength="6">
            </div>
            <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= icon('lock') ?> <?= $lang === 'bn' ? 'পাসওয়ার্ড বদলান' : 'Update Password' ?></button>
        </form>
    </div>
</div>
