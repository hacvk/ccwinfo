-- =============================================
-- 步骤 1.1：分类信息系统建表 SQL
-- 目标数据库：fd88（FastAdmin，表前缀 fa_）
-- 请手动在 MySQL 中执行本文件
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 1. fa_info_category - 分类信息的分类表
-- 避开 FastAdmin 自带的 fa_category
-- 支持两级分类（pid=0 为一级，pid>0 为二级）
-- ----------------------------
DROP TABLE IF EXISTS `fa_info_category`;
CREATE TABLE `fa_info_category` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `pid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '父级ID',
  `name` varchar(64) NOT NULL DEFAULT '' COMMENT '分类名称',
  `slug` varchar(128) DEFAULT '' COMMENT 'URL别名',
  `icon` varchar(128) DEFAULT '' COMMENT '图标',
  `weigh` int(10) NOT NULL DEFAULT '0' COMMENT '权重',
  `status` enum('normal','hidden') NOT NULL DEFAULT 'normal' COMMENT '状态:normal=启用,hidden=禁用',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_pid` (`pid`),
  KEY `idx_slug` (`slug`),
  KEY `idx_weigh` (`weigh`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='分类信息-分类表';

-- ----------------------------
-- 初始分类数据（从源项目迁移）
-- ----------------------------
BEGIN;
INSERT INTO `fa_info_category` (`id`,`pid`,`name`,`slug`,`icon`,`weigh`,`status`,`createtime`,`updatetime`) VALUES
(1, 0, '房产信息', 'fangchan', '', 10, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, 0, '人才信息', 'rencai', '', 20, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(3, 0, '供求信息', 'gongqiu', '', 30, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(4, 0, '车辆信息', 'cheliang', '', 40, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(5, 0, '出兑信息', 'chudui', '', 50, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(6, 0, '服务信息', 'fuwu', '', 60, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- 房产信息子分类
(7, 1, '租售', 'zushou', '', 10, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(8, 1, '单间/公寓', 'danjian', '', 20, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(9, 1, '个人售楼/卖房', 'shoulou', '', 30, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(10, 1, '中介/顶账房', 'zhongjie', '', 40, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(11, 1, '车库/停车位', 'cheku', '', 50, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(12, 1, '门市/商铺', 'menshi', '', 60, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(13, 1, '厂房/场地/山林/土地', 'changfang', '', 70, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- 人才信息子分类
(14, 2, '导购/店员', 'daogou', '', 10, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(15, 2, '美容/美发/化妆', 'meirong', '', 20, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(16, 2, '厨师/餐饮服务员', 'chushi', '', 30, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(17, 2, '保姆/护理', 'baomu', '', 40, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(18, 2, '司机/保安', 'siji', '', 50, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(19, 2, '快递员/配送员', 'kuaidi', '', 60, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(20, 2, '技工/普工', 'jigong', '', 70, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(21, 2, '业务/销售', 'yewu', '', 80, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(22, 2, '文员/助理', 'wenyuan', '', 90, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(23, 2, '财务/税务', 'caiwu', '', 100, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(24, 2, '经营/行政', 'jingying', '', 110, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(25, 2, '幼师/特长老师', 'youshi', '', 120, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(26, 2, '医生/护士', 'yisheng', '', 130, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(27, 2, '电商/互联网', 'dianshang', '', 140, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(28, 2, '求职（非招聘）', 'qiuzhi', '', 150, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- 供求信息子分类
(29, 3, '手机/数码', 'shouji', '', 10, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(30, 3, '卡/票', 'kapiao', '', 20, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(31, 3, '服装鞋包', 'fuzhuang', '', 30, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(32, 3, '生活日用', 'riyong', '', 40, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(33, 3, '办公/文体', 'bangong', '', 50, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(34, 3, '工具/设备', 'gongju', '', 60, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(35, 3, '食品/农副产品', 'shipin', '', 70, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(36, 3, '宠物/花卉', 'chongwu', '', 80, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(37, 3, '其他供求', 'qita-gongqiu', '', 90, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(38, 3, '寻物/失物招领', 'xunwu', '', 100, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- 车辆信息子分类
(39, 4, '新车/二手车', 'xinche', '', 10, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(40, 4, '工程/农机', 'gongcheng', '', 20, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(41, 4, '摩托/电动', 'moto', '', 30, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(42, 4, '配件/车辆服务', 'peijian', '', 40, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(43, 4, '汽车租赁', 'zulin', '', 50, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- 出兑信息子分类
(44, 5, '出兑/转让', 'chudui-zhuanrang', '', 10, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(45, 5, '招商/合作', 'zhaoshang', '', 20, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- 服务信息子分类
(46, 6, '开锁/修换锁', 'kaisuo', '', 10, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(47, 6, '装修/水电瓦木油', 'zhuangxiu', '', 20, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(48, 6, '家电/电脑维修', 'weixiu', '', 30, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(49, 6, '搬家/快递', 'banjia', '', 40, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(50, 6, '文印/广告装潢', 'wenyin', '', 50, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(51, 6, '艺术/运动/健身', 'yishu', '', 60, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(52, 6, '其他服务', 'qita-fuwu', '', 70, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
COMMIT;

-- ----------------------------
-- 2. fa_info_field - 自定义字段定义表
-- ----------------------------
DROP TABLE IF EXISTS `fa_info_field`;
CREATE TABLE `fa_info_field` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `category_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '一级分类ID',
  `sub_category_id` int(10) unsigned DEFAULT '0' COMMENT '二级分类ID(0=所有子分类通用)',
  `name` varchar(64) NOT NULL DEFAULT '' COMMENT '字段名称',
  `key` varchar(64) NOT NULL DEFAULT '' COMMENT '字段Key',
  `type` varchar(20) NOT NULL DEFAULT 'text' COMMENT '字段类型:text=文本,number=数字,date=日期,select=下拉,radio=单选,checkbox=多选,textarea=多行文本',
  `required` tinyint(1) DEFAULT '0' COMMENT '是否必填:0=否,1=是',
  `options` text COMMENT '选项配置(JSON数组)',
  `placeholder` varchar(128) DEFAULT '' COMMENT '表单提示语',
  `weigh` int(10) NOT NULL DEFAULT '0' COMMENT '权重',
  `is_filter` tinyint(1) DEFAULT '0' COMMENT '是否参与筛选:0=否,1=是',
  `is_list_show` tinyint(1) DEFAULT '0' COMMENT '是否列表展示:0=否,1=是',
  `is_detail_show` tinyint(1) DEFAULT '1' COMMENT '是否详情展示:0=否,1=是',
  `status` enum('normal','hidden') NOT NULL DEFAULT 'normal' COMMENT '状态:normal=启用,hidden=禁用',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_sub_category_id` (`sub_category_id`),
  KEY `idx_weigh` (`weigh`,`id`),
  UNIQUE KEY `uk_category_key` (`category_id`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='分类信息-自定义字段定义';

-- ----------------------------
-- 3. fa_info_field_value - 自定义字段值表
-- ----------------------------
DROP TABLE IF EXISTS `fa_info_field_value`;
CREATE TABLE `fa_info_field_value` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `info_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '信息ID',
  `field_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '字段定义ID',
  `key` varchar(64) NOT NULL DEFAULT '' COMMENT '字段Key冗余',
  `value` varchar(255) DEFAULT '' COMMENT '字段值',
  `value_number` decimal(14,2) DEFAULT NULL COMMENT '数值型字段',
  `value_date` date DEFAULT NULL COMMENT '日期型字段',
  PRIMARY KEY (`id`),
  KEY `idx_info_id` (`info_id`),
  KEY `idx_field_id` (`field_id`),
  KEY `idx_key` (`key`),
  UNIQUE KEY `uk_info_field` (`info_id`,`field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='分类信息-自定义字段值';

-- ----------------------------
-- 4. fa_info - 分类信息主表
-- ----------------------------
DROP TABLE IF EXISTS `fa_info`;
CREATE TABLE `fa_info` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '前台用户ID',
  `category_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '一级分类ID',
  `sub_category_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '二级分类ID',
  `district_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '区域ID',
  `title` varchar(64) NOT NULL DEFAULT '' COMMENT '标题',
  `slug` varchar(128) NOT NULL DEFAULT '' COMMENT 'URL别名',
  `content` text NOT NULL COMMENT '正文内容',
  `images` varchar(1500) DEFAULT '' COMMENT '图片组',
  `price` decimal(12,2) DEFAULT NULL COMMENT '价格',
  `price_unit` varchar(20) DEFAULT '' COMMENT '价格单位',
  `valid_days` int(10) NOT NULL DEFAULT '0' COMMENT '有效期天数(0=长期)',
  `valid_until` bigint(16) DEFAULT NULL COMMENT '有效期截止时间',
  `area` decimal(12,2) DEFAULT NULL COMMENT '面积',
  `area_unit` varchar(10) DEFAULT '' COMMENT '面积单位',
  `contact_name` varchar(64) DEFAULT '' COMMENT '联系人',
  `contact_phone` varchar(32) DEFAULT '' COMMENT '联系电话',
  `contact_wechat` varchar(64) DEFAULT '' COMMENT '微信号',
  `verify_phone` varchar(32) DEFAULT NULL COMMENT '验证手机号',
  `manage_password` varchar(64) DEFAULT '' COMMENT '信息管理密码',
  `ext_snapshot` text COMMENT '自定义字段快照(JSON)',
  `source_id` int(10) unsigned DEFAULT '0' COMMENT '来源ID',
  `is_top` tinyint(1) DEFAULT '0' COMMENT '是否置顶:0=否,1=是',
  `top_until` bigint(16) DEFAULT NULL COMMENT '置顶截止时间',
  `refresh_at` bigint(16) DEFAULT NULL COMMENT '擦亮时间',
  `views` int(10) unsigned DEFAULT '0' COMMENT '浏览量',
  `status` enum('draft','pending','published','rejected','expired') NOT NULL DEFAULT 'draft' COMMENT '状态:draft=草稿,pending=待审核,published=已发布,rejected=已拒绝,expired=已过期',
  `entry_admin_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '录入管理员ID',
  `entry_remark` varchar(255) DEFAULT NULL COMMENT '录入备注',
  `send_notice` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否发送通知:0=否,1=是',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_sub_category_id` (`sub_category_id`),
  KEY `idx_district_id` (`district_id`),
  KEY `idx_status` (`status`),
  KEY `idx_is_top` (`is_top`),
  KEY `idx_top_until` (`top_until`),
  KEY `idx_contact_phone` (`contact_phone`),
  KEY `idx_entry_admin_id` (`entry_admin_id`),
  KEY `idx_refresh_at` (`refresh_at`),
  KEY `idx_status_valid_until` (`status`,`valid_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='分类信息-主表';

-- ----------------------------
-- 5. fa_info_review_log - 审核日志表
-- ----------------------------
DROP TABLE IF EXISTS `fa_info_review_log`;
CREATE TABLE `fa_info_review_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `info_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '信息ID',
  `action` varchar(32) DEFAULT '' COMMENT '审核动作:approve=通过,reject=拒绝',
  `from_status` varchar(20) DEFAULT '' COMMENT '原状态',
  `to_status` varchar(20) DEFAULT '' COMMENT '目标状态',
  `reason` varchar(255) DEFAULT '' COMMENT '审核原因',
  `admin_id` int(10) unsigned DEFAULT '0' COMMENT '审核管理员ID',
  `admin_name` varchar(64) DEFAULT '' COMMENT '审核管理员名称',
  `batch_no` varchar(64) DEFAULT '' COMMENT '批次号',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_info_id` (`info_id`),
  KEY `idx_action` (`action`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_createtime` (`createtime`),
  KEY `idx_batch_no` (`batch_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='分类信息-审核日志';

SET FOREIGN_KEY_CHECKS = 1;
