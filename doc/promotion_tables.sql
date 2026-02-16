BEGIN;
CREATE TABLE `__PREFIX__promotion_slot` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `code` varchar(50) NOT NULL COMMENT '位置编码',
  `name` varchar(100) NOT NULL COMMENT '展示位名称',
  `type` enum('text','image','mix','friendlink') NOT NULL DEFAULT 'text' COMMENT '展示内容类型',
  `max_items` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最多展示条数，0表示不限制',
  `config` text COMMENT '扩展配置JSON',
  `is_system` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否系统预置位',
  `status` enum('normal','hidden') NOT NULL DEFAULT 'normal' COMMENT '状态',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='推广展示位表';
COMMIT;

BEGIN;
CREATE TABLE `__PREFIX__promotion_item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `slot_id` int(10) unsigned NOT NULL COMMENT '展示位ID',
  `title` varchar(150) NOT NULL DEFAULT '' COMMENT '标题/链接文字',
  `sub_title` varchar(255) DEFAULT NULL COMMENT '副标题/小字描述',
  `link_url` varchar(255) DEFAULT NULL COMMENT '跳转URL',
  `image` varchar(255) DEFAULT NULL COMMENT '图片路径',
  `category_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '分类ID（0为不限定）',
  `channel_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '频道ID（预留）',
  `page_scope` enum('home','category','channel','all') NOT NULL DEFAULT 'all' COMMENT '展示范围',
  `start_time` bigint(16) DEFAULT NULL COMMENT '开始时间',
  `end_time` bigint(16) DEFAULT NULL COMMENT '结束时间',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序权重，越大越前',
  `status` enum('draft','pending','online','offline','expired') NOT NULL DEFAULT 'draft' COMMENT '投放状态',
  `created_by_user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '前台提交用户ID',
  `contact_name` varchar(60) DEFAULT NULL COMMENT '联系人姓名',
  `contact_mobile` varchar(30) DEFAULT NULL COMMENT '联系人手机',
  `company_name` varchar(100) DEFAULT NULL COMMENT '公司名称',
  `review_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending' COMMENT '审核状态',
  `review_remark` varchar(255) DEFAULT NULL COMMENT '审核备注',
  `review_admin_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '审核管理员ID',
  `review_time` bigint(16) DEFAULT NULL COMMENT '审核时间',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_slot` (`slot_id`),
  KEY `idx_status_time` (`status`,`start_time`,`end_time`),
  KEY `idx_category_scope` (`category_id`,`page_scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='推广内容表';
COMMIT;

BEGIN;
CREATE TABLE `__PREFIX__promotion_order` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID/订单号',
  `item_id` int(10) unsigned NOT NULL COMMENT '关联的推广内容ID',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '前台用户ID',
  `slot_id` int(10) unsigned NOT NULL COMMENT '展示位ID',
  `category_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '分类ID（冗余）',
  `amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '订单金额',
  `pay_status` enum('unpaid','offline_paid') NOT NULL DEFAULT 'unpaid' COMMENT '支付状态',
  `order_status` enum('pending','approved','rejected','cancelled','finished') NOT NULL DEFAULT 'pending' COMMENT '订单状态',
  `contact_name` varchar(60) DEFAULT NULL COMMENT '联系人姓名',
  `contact_mobile` varchar(30) DEFAULT NULL COMMENT '联系人手机',
  `company_name` varchar(100) DEFAULT NULL COMMENT '公司名称',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `offline_voucher` varchar(255) DEFAULT NULL COMMENT '线下转账凭证',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_item` (`item_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`order_status`,`pay_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='推广订单表';
COMMIT;
