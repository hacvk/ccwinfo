-- =============================================
-- 步骤 2.1：本地攻略系统（guide）建表 SQL
-- 目标库：fd88（表前缀 fa_）
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 1) 攻略分类
-- ----------------------------
CREATE TABLE IF NOT EXISTS `fa_guide_category` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `pid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '父级ID',
  `name` varchar(64) NOT NULL DEFAULT '' COMMENT '分类名称',
  `slug` varchar(128) NOT NULL DEFAULT '' COMMENT 'URL别名',
  `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '图标',
  `weigh` int(10) NOT NULL DEFAULT '0' COMMENT '权重',
  `status` enum('normal','hidden') NOT NULL DEFAULT 'normal' COMMENT '状态',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_pid` (`pid`),
  KEY `idx_weigh` (`weigh`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='本地攻略分类';

-- ----------------------------
-- 2) 攻略模型
-- ----------------------------
CREATE TABLE IF NOT EXISTS `fa_guide_model` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(64) NOT NULL DEFAULT '' COMMENT '模型名称',
  `key` varchar(64) NOT NULL DEFAULT '' COMMENT '模型键',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '说明',
  `weigh` int(10) NOT NULL DEFAULT '0' COMMENT '权重',
  `status` enum('normal','hidden') NOT NULL DEFAULT 'normal' COMMENT '状态',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`key`),
  KEY `idx_weigh` (`weigh`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='本地攻略模型';

-- ----------------------------
-- 3) 模型扩展字段
-- ----------------------------
CREATE TABLE IF NOT EXISTS `fa_guide_field` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `model_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '模型ID',
  `name` varchar(64) NOT NULL DEFAULT '' COMMENT '字段名称',
  `key` varchar(64) NOT NULL DEFAULT '' COMMENT '字段键',
  `type` varchar(20) NOT NULL DEFAULT 'text' COMMENT '字段类型',
  `required` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否必填',
  `options` text COMMENT '选项配置(JSON)',
  `placeholder` varchar(255) NOT NULL DEFAULT '' COMMENT '占位提示',
  `weigh` int(10) NOT NULL DEFAULT '0' COMMENT '权重',
  `is_list_show` tinyint(1) NOT NULL DEFAULT '0' COMMENT '列表展示',
  `is_detail_show` tinyint(1) NOT NULL DEFAULT '1' COMMENT '详情展示',
  `status` enum('normal','hidden') NOT NULL DEFAULT 'normal' COMMENT '状态',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_model_key` (`model_id`,`key`),
  KEY `idx_model` (`model_id`),
  KEY `idx_weigh` (`weigh`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='本地攻略扩展字段定义';

-- ----------------------------
-- 4) 攻略主表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `fa_guide` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `category_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '分类ID',
  `model_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '模型ID',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '标题',
  `keywords` varchar(255) NOT NULL DEFAULT '' COMMENT '关键字',
  `summary` varchar(500) NOT NULL DEFAULT '' COMMENT '摘要',
  `content` mediumtext COMMENT '内容',
  `cover` varchar(255) NOT NULL DEFAULT '' COMMENT '封面',
  `images` text COMMENT '图集(JSON/CSV)',
  `attachments` text COMMENT '附件(JSON/CSV)',
  `source_url` varchar(500) NOT NULL DEFAULT '' COMMENT '外链地址',
  `is_external` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否外链',
  `status` enum('draft','published','offline') NOT NULL DEFAULT 'draft' COMMENT '状态',
  `publish_time` bigint(16) DEFAULT NULL COMMENT '发布时间',
  `views` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '浏览量',
  `is_top` tinyint(1) NOT NULL DEFAULT '0' COMMENT '置顶',
  `weigh` int(10) NOT NULL DEFAULT '0' COMMENT '权重',
  `admin_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '录入管理员ID',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_model` (`model_id`),
  KEY `idx_status_publish` (`status`,`publish_time`),
  KEY `idx_top` (`is_top`),
  KEY `idx_weigh` (`weigh`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='本地攻略主表';

-- ----------------------------
-- 5) 扩展字段值
-- ----------------------------
CREATE TABLE IF NOT EXISTS `fa_guide_ext_value` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `guide_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '攻略ID',
  `field_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '字段ID',
  `key` varchar(64) NOT NULL DEFAULT '' COMMENT '字段键',
  `value` text COMMENT '通用值',
  `value_number` decimal(14,2) DEFAULT NULL COMMENT '数值字段',
  `value_date` date DEFAULT NULL COMMENT '日期字段',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_guide_field` (`guide_id`,`field_id`),
  KEY `idx_guide` (`guide_id`),
  KEY `idx_field` (`field_id`),
  KEY `idx_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='本地攻略扩展字段值';

-- ----------------------------
-- 6) 标签主表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `fa_guide_tag` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(64) NOT NULL DEFAULT '' COMMENT '标签名',
  `slug` varchar(128) NOT NULL DEFAULT '' COMMENT '标签别名',
  `use_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '使用次数',
  `status` enum('normal','hidden') NOT NULL DEFAULT 'normal' COMMENT '状态',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_use_count` (`use_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='本地攻略标签';

-- ----------------------------
-- 7) 标签关系表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `fa_guide_tag_rel` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `guide_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '攻略ID',
  `tag_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '标签ID',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_guide_tag` (`guide_id`,`tag_id`),
  KEY `idx_guide` (`guide_id`),
  KEY `idx_tag` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='本地攻略标签关系';

-- ----------------------------
-- 8) 专题主表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `fa_guide_topic` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '专题标题',
  `slug` varchar(128) NOT NULL DEFAULT '' COMMENT '专题别名',
  `summary` varchar(500) NOT NULL DEFAULT '' COMMENT '摘要',
  `cover` varchar(255) NOT NULL DEFAULT '' COMMENT '封面',
  `banner` varchar(255) NOT NULL DEFAULT '' COMMENT '横幅',
  `keywords` varchar(255) NOT NULL DEFAULT '' COMMENT '关键字',
  `description` varchar(500) NOT NULL DEFAULT '' COMMENT '描述',
  `status` enum('draft','published','offline') NOT NULL DEFAULT 'draft' COMMENT '状态',
  `publish_time` bigint(16) DEFAULT NULL COMMENT '发布时间',
  `weigh` int(10) NOT NULL DEFAULT '0' COMMENT '权重',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_status_publish` (`status`,`publish_time`),
  KEY `idx_weigh` (`weigh`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='本地攻略专题';

-- ----------------------------
-- 9) 专题模块定义
-- ----------------------------
CREATE TABLE IF NOT EXISTS `fa_guide_topic_block` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `topic_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '专题ID',
  `type` enum('manual_list','rule_list','external_cards') NOT NULL DEFAULT 'manual_list' COMMENT '模块类型',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '模块标题',
  `rule_json` text COMMENT '规则JSON',
  `weigh` int(10) NOT NULL DEFAULT '0' COMMENT '权重',
  `status` enum('normal','hidden') NOT NULL DEFAULT 'normal' COMMENT '状态',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_topic` (`topic_id`),
  KEY `idx_type` (`type`),
  KEY `idx_weigh` (`weigh`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='本地攻略专题模块';

-- ----------------------------
-- 10) 专题模块条目
-- ----------------------------
CREATE TABLE IF NOT EXISTS `fa_guide_topic_block_item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `block_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '模块ID',
  `item_type` enum('guide','external') NOT NULL DEFAULT 'guide' COMMENT '条目类型',
  `guide_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '攻略ID',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '标题',
  `url` varchar(500) NOT NULL DEFAULT '' COMMENT '链接',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '封面',
  `summary` varchar(500) NOT NULL DEFAULT '' COMMENT '摘要',
  `weigh` int(10) NOT NULL DEFAULT '0' COMMENT '权重',
  `status` enum('normal','hidden') NOT NULL DEFAULT 'normal' COMMENT '状态',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_block` (`block_id`),
  KEY `idx_item_type` (`item_type`),
  KEY `idx_guide` (`guide_id`),
  KEY `idx_weigh` (`weigh`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='本地攻略专题模块条目';

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'guide tables create done' AS message;

