<?php
declare(strict_types=1);

return [
    'description' => 'Encrypted admin TOTP secrets, TOTP replay step tracking & global daily SMS budget',
    'up' => static function (PDO $pdo): void {
        migration_ensure_tracking_table($pdo);
        mig_modify_column_if_exists($pdo, 'admins', 'totp_secret', 'VARCHAR(255) DEFAULT NULL');
        mig_add_column_if_missing($pdo, 'admins', 'totp_last_step', 'BIGINT DEFAULT NULL AFTER `backup_codes_json`');
        mig_insert_setting_if_missing($pdo, 'sms_daily_budget', '200');
    },
];
