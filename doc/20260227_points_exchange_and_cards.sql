-- 2026-02-27
-- Points exchange + recharge/discount cards
-- Idempotent migration for existing projects

SET @db = DATABASE();

CREATE TABLE IF NOT EXISTS `fa_recharge_card` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_no` varchar(64) NOT NULL DEFAULT '' COMMENT 'Card number',
  `card_secret` varchar(128) NOT NULL DEFAULT '' COMMENT 'Card secret',
  `card_type` enum('recharge','discount') NOT NULL DEFAULT 'recharge' COMMENT 'Card type',
  `face_amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT 'Face amount',
  `bonus_rate` decimal(8,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT 'Bonus rate',
  `min_recharge` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT 'Min recharge amount',
  `max_bonus` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT 'Max bonus amount',
  `batch_no` varchar(50) NOT NULL DEFAULT '' COMMENT 'Batch number',
  `owner_user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Owner user id',
  `status` enum('unused','reserved','issued','used','disabled') NOT NULL DEFAULT 'unused' COMMENT 'Status',
  `expiretime` bigint(16) DEFAULT NULL COMMENT 'Expire time',
  `reserve_orderid` varchar(100) NOT NULL DEFAULT '' COMMENT 'Reserved order id',
  `used_user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Used by user id',
  `used_orderid` varchar(100) NOT NULL DEFAULT '' COMMENT 'Used order id',
  `used_time` bigint(16) DEFAULT NULL COMMENT 'Used time',
  `createtime` bigint(16) DEFAULT NULL COMMENT 'Create time',
  `updatetime` bigint(16) DEFAULT NULL COMMENT 'Update time',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_card_no` (`card_no`),
  KEY `idx_card_type_status` (`card_type`,`status`),
  KEY `idx_owner_status` (`owner_user_id`,`status`),
  KEY `idx_batch_no` (`batch_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Recharge/discount card table';

CREATE TABLE IF NOT EXISTS `fa_exchange_goods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL DEFAULT '' COMMENT 'Title',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT 'Image',
  `goods_type` enum('physical','virtual') NOT NULL DEFAULT 'virtual' COMMENT 'Goods type',
  `virtual_mode` enum('internal_recharge_card','internal_discount_card','manual') DEFAULT 'manual' COMMENT 'Virtual mode',
  `virtual_source` enum('inside','outside') DEFAULT 'outside' COMMENT 'Virtual source',
  `points_cost` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Points cost',
  `stock` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Available stock',
  `locked_stock` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Locked stock',
  `per_user_limit` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Per user limit, 0 means unlimited',
  `need_shipping` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Need shipping address',
  `delivery_note` varchar(500) NOT NULL DEFAULT '' COMMENT 'Delivery note',
  `description` text COMMENT 'Description',
  `status` enum('normal','hidden') NOT NULL DEFAULT 'normal' COMMENT 'Status',
  `weigh` int(10) NOT NULL DEFAULT '0' COMMENT 'Weigh',
  `start_time` bigint(16) DEFAULT NULL COMMENT 'Start time',
  `end_time` bigint(16) DEFAULT NULL COMMENT 'End time',
  `createtime` bigint(16) DEFAULT NULL COMMENT 'Create time',
  `updatetime` bigint(16) DEFAULT NULL COMMENT 'Update time',
  PRIMARY KEY (`id`),
  KEY `idx_status_time` (`status`,`start_time`,`end_time`),
  KEY `idx_goods_type` (`goods_type`),
  KEY `idx_weigh` (`weigh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Points exchange goods';

CREATE TABLE IF NOT EXISTS `fa_exchange_order` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(40) NOT NULL DEFAULT '' COMMENT 'Order number',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'User id',
  `goods_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Goods id',
  `goods_snapshot` text COMMENT 'Goods snapshot JSON',
  `points_cost` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Points cost',
  `points_status` enum('undeducted','deducted','refunded') NOT NULL DEFAULT 'undeducted' COMMENT 'Points status',
  `order_status` enum('pending','delivered','shipped','completed','cancelled','rejected') NOT NULL DEFAULT 'pending' COMMENT 'Order status',
  `delivery_content` text COMMENT 'Delivery content',
  `province_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Province id',
  `city_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'City id',
  `district_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'District id',
  `province_name` varchar(60) NOT NULL DEFAULT '' COMMENT 'Province name',
  `city_name` varchar(60) NOT NULL DEFAULT '' COMMENT 'City name',
  `district_name` varchar(60) NOT NULL DEFAULT '' COMMENT 'District name',
  `address_detail` varchar(255) NOT NULL DEFAULT '' COMMENT 'Address detail',
  `receiver_name` varchar(60) NOT NULL DEFAULT '' COMMENT 'Receiver name',
  `receiver_mobile` varchar(30) NOT NULL DEFAULT '' COMMENT 'Receiver mobile',
  `cancel_reason` varchar(255) NOT NULL DEFAULT '' COMMENT 'Cancel reason',
  `admin_remark` varchar(255) NOT NULL DEFAULT '' COMMENT 'Admin remark',
  `deliver_time` bigint(16) DEFAULT NULL COMMENT 'Deliver time',
  `cancel_time` bigint(16) DEFAULT NULL COMMENT 'Cancel time',
  `createtime` bigint(16) DEFAULT NULL COMMENT 'Create time',
  `updatetime` bigint(16) DEFAULT NULL COMMENT 'Update time',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_user_status` (`user_id`,`order_status`),
  KEY `idx_goods_status` (`goods_id`,`order_status`),
  KEY `idx_points_status` (`points_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Points exchange orders';

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_recharge_order'
       AND COLUMN_NAME = 'recharge_card_id') = 0,
    'ALTER TABLE `fa_recharge_order` ADD COLUMN `recharge_card_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT ''Recharge card id'' AFTER `paytype`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_recharge_order'
       AND COLUMN_NAME = 'discount_card_id') = 0,
    'ALTER TABLE `fa_recharge_order` ADD COLUMN `discount_card_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT ''Discount card id'' AFTER `recharge_card_id`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_recharge_order'
       AND COLUMN_NAME = 'bonus_amount') = 0,
    'ALTER TABLE `fa_recharge_order` ADD COLUMN `bonus_amount` decimal(10,2) unsigned NOT NULL DEFAULT 0.00 COMMENT ''Bonus amount'' AFTER `discount_card_id`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_recharge_order'
       AND INDEX_NAME = 'idx_card_info') = 0,
    'ALTER TABLE `fa_recharge_order` ADD KEY `idx_card_info` (`recharge_card_id`,`discount_card_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
