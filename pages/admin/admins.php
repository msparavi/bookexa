<?php
declare(strict_types=1);

$lang = current_lang();
$adminsList = db_all('SELECT id, name, email, role, is_active, last_login_at, created_at FROM admins ORDER BY id ASC');
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$editAdmin = $editId ? db_one('SELECT id, name, email, role, is_active FROM admins WHERE id = ?', [$editId]) : null;
$auditLogs = db_all('SELECT * FROM admin_logs ORDER BY id DESC LIMIT 100');
?>

<div class="bx-grid-2" style="align-items:start;margin-bottom:22px;">
    <!-- Admin Accounts List -->
    <section class="bx-card">
        <h2 style="margin:0 0 14px;font-size:1.1rem;font-family:var(--font-serif);">
            <?= $lang === 'bn' ? 'অ্যাডমিন অ্যাকাউন্ট ও ভূমিকা' : 'Admin Accounts & Roles' ?>
        </h2>
        <div class="bx-table-wrap">
            <table class="bx-table bx-mobile-cards">
                <thead>
                    <tr>
                        <th><?= $lang === 'bn' ? 'নাম / ইমেইল' : 'Admin' ?></th>
                        <th><?= $lang === 'bn' ? 'ভূমিকা' : 'Role' ?></th>
                        <th><?= $lang === 'bn' ? 'অবস্থা' : 'Status' ?></th>
                        <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Actions' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($adminsList as $a): ?>
                        <?php $aid = (int) $a['id']; $isSelf = $aid === (int) $admin['id']; ?>
                        <tr>
                            <td class="bx-td-primary">
                                <strong><?= e($a['name']) ?></strong> <?= $isSelf ? '<span class="bx-badge">You</span>' : '' ?>
                                <span class="bx-ellipsis" style="font-size:0.78rem;color:var(--text-muted);"><?= e($a['email']) ?></span>
                            </td>
                            <td data-label="<?= e($lang === 'bn' ? 'রোল' : 'Role') ?>">
                                <span class="bx-status-pill <?= $a['role'] === 'superadmin' ? 'st-pending' : 'st-active' ?>">
                                    <?= e(strtoupper($a['role'])) ?>
                                </span>
                            </td>
                            <td data-label="<?= e($lang === 'bn' ? 'অবস্থা' : 'Status') ?>">
                                <span class="bx-status-pill <?= (int) $a['is_active'] ? 'st-active' : 'st-inactive' ?>">
                                    <?= (int) $a['is_active'] ? ($lang === 'bn' ? 'সক্রিয়' : 'Active') : ($lang === 'bn' ? 'নিষ্ক্রিয়' : 'Disabled') ?>
                                </span>
                            </td>
                            <td class="bx-td-actions">
                                <a href="<?= e(url('/admin/admins?edit=' . $aid . '#admin-form')) ?>" class="bx-btn bx-btn-outline" style="padding:8px 12px;font-size:0.84rem;"><?= $lang === 'bn' ? 'সম্পাদনা' : 'Edit' ?></a>
                                <?php if (!$isSelf): ?>
                                    <form method="post" action="<?= e(url('/admin/admins')) ?>" data-confirm-msg="<?= e($lang === 'bn' ? 'এই অ্যাডমিন অ্যাকাউন্টের অবস্থা পরিবর্তন করবেন?' : 'Toggle active status for this admin?') ?>" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_admin">
                                        <input type="hidden" name="admin_id" value="<?= $aid ?>">
                                        <button type="submit" class="bx-btn bx-btn-ghost" style="padding:8px 12px;font-size:0.84rem;">
                                            <?= (int) $a['is_active'] ? ($lang === 'bn' ? 'বন্ধ' : 'Disable') : ($lang === 'bn' ? 'চালু' : 'Enable') ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Create / Edit Admin Form -->
    <section class="bx-card" id="admin-form">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <h2 style="margin:0;font-size:1.1rem;font-family:var(--font-serif);">
                <?= $editAdmin ? ($lang === 'bn' ? 'অ্যাডমিন সম্পাদনা' : 'Edit Admin Account') : ($lang === 'bn' ? 'নতুন অ্যাডমিন তৈরি' : 'Add Admin / Editor') ?>
            </h2>
            <?php if ($editAdmin): ?>
                <a href="<?= e(url('/admin/admins')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'বাতিল' : 'Reset' ?></a>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= e(url('/admin/admins')) ?>" class="bx-track-dirty">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_admin">
            <?php if ($editAdmin): ?>
                <input type="hidden" name="id" value="<?= (int) $editAdmin['id'] ?>">
            <?php endif; ?>

            <div class="bx-field">
                <label><?= $lang === 'bn' ? 'নাম *' : 'Full Name *' ?></label>
                <input type="text" name="name" class="bx-input" required value="<?= e((string) ($editAdmin['name'] ?? '')) ?>">
            </div>

            <div class="bx-field">
                <label><?= $lang === 'bn' ? 'ইমেইল *' : 'Email Address *' ?></label>
                <input type="email" name="email" class="bx-input" required value="<?= e((string) ($editAdmin['email'] ?? '')) ?>">
            </div>

            <div class="bx-grid-2" style="gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'রোল' : 'Role' ?></label>
                    <select name="role" class="bx-input">
                        <option value="editor" <?= ($editAdmin['role'] ?? 'editor') === 'editor' ? 'selected' : '' ?>>Editor (Content & Orders)</option>
                        <option value="superadmin" <?= ($editAdmin['role'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Super Admin (Full Control)</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $editAdmin ? ($lang === 'bn' ? 'নতুন পাসওয়ার্ড (ঐচ্ছিক)' : 'New Password (Optional)') : ($lang === 'bn' ? 'পাসওয়ার্ড (৮+ অক্ষর) *' : 'Password (8+ chars) *') ?></label>
                    <input type="password" name="password" class="bx-input" <?= $editAdmin ? '' : 'required minlength="8"' ?> autocomplete="new-password" placeholder="••••••••">
                </div>
            </div>

            <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:600;">
                <input type="checkbox" name="is_active" value="1" <?= !$editAdmin || (int) $editAdmin['is_active'] ? 'checked' : '' ?>>
                <?= $lang === 'bn' ? 'অ্যাকাউন্ট সক্রিয়' : 'Account Active' ?>
            </label>
            <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:600;">
                <input type="checkbox" name="must_change_password" value="1" <?= !$editAdmin ? 'checked' : '' ?>>
                <?= $lang === 'bn' ? 'প্রথম লগইনে পাসওয়ার্ড পরিবর্তন বাধ্যতামূলক' : 'Require password change on next login' ?>
            </label>

            <div class="bx-sticky-form-actions">
                <button type="submit" class="bx-btn bx-btn-primary">
                    <?= $editAdmin ? ($lang === 'bn' ? 'আপডেট করুন' : 'Update Admin') : ($lang === 'bn' ? 'অ্যাডমিন তৈরি করুন' : 'Create Admin') ?>
                </button>
                <?php if ($editAdmin): ?>
                    <a href="<?= e(url('/admin/admins')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'বাতিল' : 'Cancel' ?></a>
                <?php endif; ?>
            </div>
        </form>
    </section>
</div>

<!-- Full Admin Activity Log -->
<section class="bx-card">
    <h2 style="margin:0 0 12px;font-size:1.1rem;font-family:var(--font-serif);">
        <?= $lang === 'bn' ? 'অ্যাডমিন অডিট লগ' : 'Admin Activity Audit Log' ?> (<?= count($auditLogs) ?>)
    </h2>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'অ্যাডমিন' : 'Admin' ?></th>
                    <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Action' ?></th>
                    <th><?= $lang === 'bn' ? 'লক্ষ্য' : 'Target' ?></th>
                    <th><?= $lang === 'bn' ? 'বিবরণ' : 'Details' ?></th>
                    <th>IP</th>
                    <th><?= $lang === 'bn' ? 'সময়' : 'Timestamp' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$auditLogs): ?>
                    <tr class="bx-empty-row"><td colspan="6" style="color:var(--text-muted);padding:20px;"><?= $lang === 'bn' ? 'কোনো অডিট লগ নেই।' : 'No admin activity logged yet.' ?></td></tr>
                <?php else: ?>
                    <?php foreach ($auditLogs as $log): ?>
                        <tr>
                            <td class="bx-td-primary"><strong><?= e($log['admin_name']) ?></strong></td>
                            <td data-label="<?= e($lang === 'bn' ? 'অ্যাকশন' : 'Action') ?>"><span class="bx-badge"><?= e($log['action']) ?></span></td>
                            <td data-label="<?= e($lang === 'bn' ? 'লক্ষ্য' : 'Target') ?>"><?= e((string) ($log['target_type'] ?? '—')) ?><?= $log['target_id'] ? ' #' . (int) $log['target_id'] : '' ?></td>
                            <td data-label="<?= e($lang === 'bn' ? 'বিবরণ' : 'Details') ?>" style="font-size:0.84rem;color:var(--text-muted);"><?= e((string) ($log['details'] ?? '')) ?></td>
                            <td data-label="IP"><code><?= e($log['ip_address']) ?></code></td>
                            <td data-label="<?= e($lang === 'bn' ? 'সময়' : 'When') ?>" style="font-size:0.8rem;color:var(--text-muted);"><?= e($log['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
