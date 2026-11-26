-- Bookexa Migration 006: Order lifecycle (cancelled/expired), internal notes, payment screenshots & gateway adapter settings

ALTER TABLE `orders`
    MODIFY COLUMN `payment_method` VARCHAR(40) NOT NULL DEFAULT 'bkash',
    ADD COLUMN `gateway_driver` VARCHAR(40) DEFAULT NULL AFTER `payment_method`,
    ADD COLUMN `gateway_ref` VARCHAR(120) DEFAULT NULL AFTER `gateway_driver`,
    ADD COLUMN `payment_screenshot` VARCHAR(255) DEFAULT NULL AFTER `transaction_id`,
    MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected', 'cancelled', 'expired') NOT NULL DEFAULT 'pending',
    ADD COLUMN `internal_notes` TEXT DEFAULT NULL AFTER `admin_note`,
    ADD KEY `idx_orders_created` (`created_at`);

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('pending_order_expire_days', '3'),
('payment_gateway_enabled', '0'),
('payment_gateway_driver', 'manual'),
('payment_gateway_sandbox', '1'),
('payment_gateway_base_url', ''),
('payment_gateway_api_key', ''),
('payment_gateway_api_secret', ''),
('payment_gateway_webhook_secret', '')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;
