<?php
declare(strict_types=1);

return [
    'description' => 'White-label theme colors/fonts, footer/social links & Demo Mode settings',
    'up' => static function (PDO $pdo): void {
        migration_ensure_tracking_table($pdo);
        mig_insert_setting_if_missing($pdo, 'theme_primary_color', '#2B2F77');
        mig_insert_setting_if_missing($pdo, 'theme_accent_color', '#F4A62A');
        mig_insert_setting_if_missing($pdo, 'theme_font_pair', 'hind_noto');
        mig_insert_setting_if_missing($pdo, 'footer_text_bn', '');
        mig_insert_setting_if_missing($pdo, 'footer_text_en', '');
        mig_insert_setting_if_missing($pdo, 'social_facebook', '');
        mig_insert_setting_if_missing($pdo, 'social_youtube', '');
        mig_insert_setting_if_missing($pdo, 'social_telegram', '');
        mig_insert_setting_if_missing($pdo, 'social_whatsapp', '');
        mig_insert_setting_if_missing($pdo, 'social_instagram', '');
        mig_insert_setting_if_missing($pdo, 'demo_mode', '0');
    },
];
