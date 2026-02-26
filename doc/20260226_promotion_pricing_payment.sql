-- promotion pricing, dynamic form and payment fields (idempotent)
SET @db = DATABASE();

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_promotion_item'
       AND COLUMN_NAME = 'form_data') = 0,
    'ALTER TABLE `fa_promotion_item` ADD COLUMN `form_data` TEXT NULL COMMENT ''动态表单数据JSON'' AFTER `company_name`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_promotion_item'
       AND COLUMN_NAME = 'billing_cycle') = 0,
    'ALTER TABLE `fa_promotion_item` ADD COLUMN `billing_cycle` enum(''week'',''month'',''quarter'') NULL COMMENT ''计费周期'' AFTER `form_data`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_promotion_item'
       AND COLUMN_NAME = 'duration_days') = 0,
    'ALTER TABLE `fa_promotion_item` ADD COLUMN `duration_days` int(10) unsigned NOT NULL DEFAULT 0 COMMENT ''投放天数'' AFTER `billing_cycle`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_promotion_order'
       AND COLUMN_NAME = 'pay_status'
       AND COLUMN_TYPE LIKE '%''paid''%'
       AND COLUMN_TYPE LIKE '%''cancelled''%') = 0,
    'ALTER TABLE `fa_promotion_order` MODIFY COLUMN `pay_status` enum(''unpaid'',''paid'',''offline_paid'',''cancelled'') NOT NULL DEFAULT ''unpaid'' COMMENT ''支付状态''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_promotion_order'
       AND COLUMN_NAME = 'order_no') = 0,
    'ALTER TABLE `fa_promotion_order` ADD COLUMN `order_no` varchar(32) DEFAULT NULL COMMENT ''订单号'' AFTER `id`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_promotion_order'
       AND COLUMN_NAME = 'pay_type') = 0,
    'ALTER TABLE `fa_promotion_order` ADD COLUMN `pay_type` enum(''wechat'',''alipay'',''offline'') DEFAULT NULL COMMENT ''支付通道'' AFTER `pay_status`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_promotion_order'
       AND COLUMN_NAME = 'pay_time') = 0,
    'ALTER TABLE `fa_promotion_order` ADD COLUMN `pay_time` bigint(16) DEFAULT NULL COMMENT ''支付时间'' AFTER `pay_type`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_promotion_order'
       AND COLUMN_NAME = 'pay_trade_no') = 0,
    'ALTER TABLE `fa_promotion_order` ADD COLUMN `pay_trade_no` varchar(64) DEFAULT NULL COMMENT ''第三方交易号'' AFTER `pay_time`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_promotion_order'
       AND COLUMN_NAME = 'paid_amount') = 0,
    'ALTER TABLE `fa_promotion_order` ADD COLUMN `paid_amount` decimal(10,2) unsigned NOT NULL DEFAULT 0.00 COMMENT ''实付金额'' AFTER `pay_trade_no`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_promotion_order'
       AND INDEX_NAME = 'uk_order_no') = 0,
    'ALTER TABLE `fa_promotion_order` ADD UNIQUE KEY `uk_order_no` (`order_no`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_promotion_order'
       AND INDEX_NAME = 'idx_pay_status') = 0,
    'ALTER TABLE `fa_promotion_order` ADD KEY `idx_pay_status` (`pay_status`,`order_status`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_promotion_order'
       AND INDEX_NAME IN ('idx_item_id', 'idx_item')) = 0,
    'ALTER TABLE `fa_promotion_order` ADD KEY `idx_item_id` (`item_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
