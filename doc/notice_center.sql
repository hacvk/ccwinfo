-- =============================================
-- 通知中心模块数据库脚本
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 1. fa_sms_log - 短信发送日志表
-- ----------------------------
DROP TABLE IF EXISTS `fa_sms_log`;
CREATE TABLE `fa_sms_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL DEFAULT '' COMMENT '手机号',
  `template_code` varchar(64) DEFAULT '' COMMENT '短信模板Code',
  `scene` varchar(32) DEFAULT '' COMMENT '业务场景',
  `channel` varchar(32) DEFAULT 'qcloud' COMMENT '发送渠道(qcloud/aliyun)',
  `success` tinyint(1) DEFAULT 0 COMMENT '是否成功',
  `provider_biz_id` varchar(64) DEFAULT '' COMMENT '服务商回执ID',
  `request_payload` text COMMENT '请求参数',
  `error` varchar(500) DEFAULT '' COMMENT '错误信息',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_scene` (`scene`),
  KEY `idx_channel` (`channel`),
  KEY `idx_success` (`success`),
  KEY `idx_createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='短信发送日志';

-- ----------------------------
-- 2. 扩展 fa_sms 表 - 添加验证码相关字段（如不存在）
-- 注意：如果字段已存在，请跳过对应的 ALTER 语句
-- ----------------------------
-- 以下字段如果不存在则添加，请根据实际情况执行

-- 添加 type 字段（如不存在）
-- ALTER TABLE `fa_sms` ADD COLUMN `type` varchar(32) DEFAULT '' COMMENT '验证码类型' AFTER `event`;

-- 添加 info_id 字段（如不存在）
-- ALTER TABLE `fa_sms` ADD COLUMN `info_id` int(10) unsigned DEFAULT 0 COMMENT '关联信息ID' AFTER `type`;

-- 添加 expire_at 字段（如不存在）
-- ALTER TABLE `fa_sms` ADD COLUMN `expire_at` bigint(16) DEFAULT NULL COMMENT '过期时间' AFTER `createtime`;

-- 添加 used_at 字段（如不存在）
-- ALTER TABLE `fa_sms` ADD COLUMN `used_at` bigint(16) DEFAULT NULL COMMENT '使用时间' AFTER `expire_at`;

-- 添加索引（如不存在）
-- ALTER TABLE `fa_sms` ADD INDEX `idx_type` (`type`);
-- ALTER TABLE `fa_sms` ADD INDEX `idx_info_id` (`info_id`);
-- ALTER TABLE `fa_sms` ADD INDEX `idx_expire_at` (`expire_at`);

-- ----------------------------
-- 3. 添加阿里云短信配置项
-- ----------------------------
INSERT IGNORE INTO `fa_config` (`name`, `group`, `title`, `tip`, `type`, `visible`, `value`, `content`, `rule`, `extend`, `setting`) VALUES
('aliyun_sms_access_key_id', 'sms', '阿里云AccessKey ID', '阿里云短信服务的 AccessKey ID', 'string', '', '', '', '', '', ''),
('aliyun_sms_access_key_secret', 'sms', '阿里云AccessKey Secret', '阿里云短信服务的 AccessKey Secret', 'password', '', '', '', '', '', ''),
('aliyun_sms_sign_name', 'sms', '阿里云短信签名', '在阿里云短信控制台申请并通过审核的短信签名', 'string', '', '', '', '', '', ''),
('aliyun_sms_region_id', 'sms', '阿里云地域ID', '阿里云短信服务地域，默认为 cn-hangzhou', 'string', '', 'cn-hangzhou', '', '', '', ''),
('aliyun_sms_endpoint', 'sms', '阿里云API Endpoint', '阿里云短信 API 端点，默认为 dysmsapi.aliyuncs.com', 'string', '', 'dysmsapi.aliyuncs.com', '', '', '', '');

-- ----------------------------
-- 4. 添加通知中心菜单
-- ----------------------------
INSERT IGNORE INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('menu', 0, 'notice', '通知中心', 'fa fa-bell', '', '', '通知中心管理', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

SET @notice_id = LAST_INSERT_ID();

INSERT IGNORE INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', @notice_id, 'notice/index', '查看', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @notice_id, 'notice/logs', '短信日志', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @notice_id, 'notice/otp', '验证码记录', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @notice_id, 'notice/aliyun', '阿里云短信配置', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @notice_id, 'notice/testAliyunSms', '测试短信发送', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @notice_id, 'notice/cleanup', '清理历史数据', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

SET FOREIGN_KEY_CHECKS = 1;
