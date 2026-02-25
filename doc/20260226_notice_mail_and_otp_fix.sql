-- =============================================
-- 通知中心邮件配置 + 验证码记录修复
-- 2026-02-26
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

SET @db = DATABASE();

-- ----------------------------
-- 1) fa_sms 扩展字段（幂等）
-- ----------------------------
SET @sql = IF(
    (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fa_sms' AND COLUMN_NAME='type') = 0,
    'ALTER TABLE `fa_sms` ADD COLUMN `type` varchar(32) DEFAULT '''' COMMENT ''验证码类型'' AFTER `event`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fa_sms' AND COLUMN_NAME='info_id') = 0,
    'ALTER TABLE `fa_sms` ADD COLUMN `info_id` int(10) unsigned DEFAULT 0 COMMENT ''关联信息ID'' AFTER `type`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fa_sms' AND COLUMN_NAME='expire_at') = 0,
    'ALTER TABLE `fa_sms` ADD COLUMN `expire_at` bigint(16) DEFAULT NULL COMMENT ''过期时间'' AFTER `createtime`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fa_sms' AND COLUMN_NAME='used_at') = 0,
    'ALTER TABLE `fa_sms` ADD COLUMN `used_at` bigint(16) DEFAULT NULL COMMENT ''使用时间'' AFTER `expire_at`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fa_sms' AND INDEX_NAME='idx_type') = 0,
    'ALTER TABLE `fa_sms` ADD INDEX `idx_type` (`type`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fa_sms' AND INDEX_NAME='idx_info_id') = 0,
    'ALTER TABLE `fa_sms` ADD INDEX `idx_info_id` (`info_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fa_sms' AND INDEX_NAME='idx_expire_at') = 0,
    'ALTER TABLE `fa_sms` ADD INDEX `idx_expire_at` (`expire_at`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------
-- 2) 补齐邮件配置项（幂等）
-- ----------------------------
INSERT IGNORE INTO `fa_config` (`name`, `group`, `title`, `tip`, `type`, `visible`, `value`, `content`, `rule`, `extend`, `setting`) VALUES
('mail_type', 'email', 'Mail type', '选择邮件发送方式', 'select', '', '1', '["请选择","SMTP"]', '', '', ''),
('mail_smtp_host', 'email', 'Mail smtp host', '错误的配置发送邮件会导致服务器超时', 'string', '', 'smtp.qq.com', '', '', '', ''),
('mail_smtp_port', 'email', 'Mail smtp port', '(不加密默认25,SSL默认465,TLS默认587)', 'string', '', '465', '', '', '', ''),
('mail_smtp_user', 'email', 'Mail smtp user', '（填写完整用户名）', 'string', '', '', '', '', '', ''),
('mail_smtp_pass', 'email', 'Mail smtp password', '（填写您的密码或授权码）', 'password', '', '', '', '', '', ''),
('mail_verify_type', 'email', 'Mail vertify type', '（SMTP验证方式[推荐SSL]）', 'select', '', '2', '["无","TLS","SSL"]', '', '', ''),
('mail_from', 'email', 'Mail from', '', 'string', '', '', '', '', '', '');

-- ----------------------------
-- 3) 通知中心权限节点（幂等）
-- ----------------------------
SET @notice_id = (
    SELECT `id` FROM `fa_auth_rule`
    WHERE `name`='notice'
    ORDER BY `id` ASC
    LIMIT 1
);

INSERT IGNORE INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', IFNULL(@notice_id, 0), 'notice/mail', '邮件配置', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', IFNULL(@notice_id, 0), 'notice/testMail', '测试邮件发送', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

SET FOREIGN_KEY_CHECKS = 1;
