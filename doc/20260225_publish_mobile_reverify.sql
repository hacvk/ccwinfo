-- 发布前手机号首发与月度复验策略
-- 执行前请确认数据库前缀为 fa_

ALTER TABLE `fa_user`
  ADD COLUMN `mobile_verify_at` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '最近一次发布手机号验证通过时间' AFTER `verification`,
  ADD COLUMN `mobile_verify_exempt_until` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '未绑定手机号跳过绑定的临时豁免截止时间' AFTER `mobile_verify_at`;

CREATE TABLE IF NOT EXISTS `fa_policy_config` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `policy_key` varchar(64) NOT NULL DEFAULT '' COMMENT '策略键',
  `policy_value` varchar(255) NOT NULL DEFAULT '' COMMENT '策略值',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `createtime` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updatetime` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_policy_key` (`policy_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='业务策略配置';

INSERT INTO `fa_policy_config` (`policy_key`, `policy_value`, `remark`, `createtime`, `updatetime`) VALUES
('publish_mobile_reverify_days', '30', '登录用户发布手机号复验周期（天）', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('publish_mobile_skip_bind_exempt_days', '7', '无手机号用户验证后不绑定时的发布豁免天数', UNIX_TIMESTAMP(), UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
  `policy_value` = VALUES(`policy_value`),
  `remark` = VALUES(`remark`),
  `updatetime` = VALUES(`updatetime`);
