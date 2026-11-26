<?php
declare(strict_types=1);

return [
    'description' => 'Order lifecycle statuses, payment screenshots, internal notes & gateway settings',
    'up' => static function (PDO $pdo): void {
        mig_modify_column_if_exists($pdo, 'orders', 'payment_method', "VARCHAR(40) NOT NULL DEFAULT 'bkash'");
        mig_add_column_if_missing($pdo, 'orders', 'gateway_driver', 'VARCHAR(40) DEFAULT NULL AFTER `payment_method`');
        mig_add_column_if_missing($pdo, 'orders', 'gateway_ref', 'VARCHAR(120) DEFAULT NULL AFTER `gateway_driver`');
        mig_add_column_if_missing($pdo, 'orders', 'payment_screenshot', 'VARCHAR(255) DEFAULT NULL AFTER `transaction_id`');
        mig_modify_column_if_exists($pdo, 'orders', 'status', "ENUM('pending', 'approved', 'rejected', 'cancelled', 'expired') NOT NULL DEFAULT 'pending'");
        mig_add_column_if_missing($pdo, 'orders', 'internal_notes', 'TEXT DEFAULT NULL AFTER `admin_note`');
        mig_add_index_if_missing($pdo, 'orders', 'idx_orders_created', 'KEY `idx_orders_created` (`created_at`)');

        foreach ([
            'pending_order_expire_days'      => '3',
            'payment_gateway_enabled'        => '0',
            'payment_gateway_driver'         => 'manual',
            'payment_gateway_sandbox'        => '1',
            'payment_gateway_base_url'       => '',
            'payment_gateway_api_key'        => '',
            'payment_gateway_api_secret'     => '',
            'payment_gateway_webhook_secret' => '',
        ] as $k => $v) {
            mig_insert_setting_if_missing($pdo, $k, $v);
        }
    },
];
