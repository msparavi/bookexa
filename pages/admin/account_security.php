<?php
declare(strict_types=1);

$lang = current_lang();
$forced = !empty($admin['must_change_password']);
?>

<section class="bx-card" style="max-width:540px;margin:0 auto;">
    <?php if ($forced): ?>
        <div class="bx-alert bx-alert-warning" role="alert" style="margin-bottom:16px;">
            <?= $lang === 'bn'
                ? 'নিরাপত্তার জন্য প্রথমবার লগইনের পর আপনাকে অবশ্যই ডিফল্ট বা অস্থায়ী পাসওয়ার্ড পরিবর্তন করতে হবে।'
                : 'Security required: Please replace your initial or default password before using the Admin Panel.' ?>
        </div>
    <?php endif; ?>

    <h2 style="margin:0 0 8px;font-size:1.2rem;font-family:var(--font-serif);">
        <?= $lang === 'bn' ? 'অ্যাডমিন পাসওয়ার্ড পরিবর্তন' : 'Change Admin Password' ?>
    </h2>
    <p style="margin:0 0 18px;color:var(--text-muted);font-size:0.9rem;">
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
